# Phase 1: 最小デプロイ(FrankenPHP classic mode / パブリックサブネット直結)

**ステータス**: ✅ 完了  
**所要時間**: 約60分

---

## 目的
ECS Fargate上でLaravelコンテナを動かし、ブラウザからアクセスできることを確認する。最もシンプルな構成(ALBなし / パブリックIP直結)でAWSの基本的なデプロイフローを学ぶ。

---

## 構成

```
Internet
    ↓ HTTP:8000(直接)
ECS Task (Fargate) [Public subnet / Public IP]
    ↓ TCP:5432
RDS PostgreSQL [Default VPC / Publicly Accessible: No]
```

> この構成はPhase 3で破棄し、プライベートサブネット + ALB構成に移行する。

---

## 手順

### 1-1. ECRリポジトリ作成
検索バー → `ECR` → 「リポジトリを作成」

| 項目 | 値 |
|---|---|
| 表示設定 | プライベート |
| リポジトリ名 | `laravel-app` |

### 1-2. DockerイメージのビルドとECRへのpush

> ⚠️ **Apple Silicon(arm64)の場合は`--platform linux/amd64`が必須**

```bash
# ECRへのログイン
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com

# ビルド
docker build --platform linux/amd64 -t laravel-app .

# タグ付け・push
docker tag laravel-app:latest \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest
docker push \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest
```

### 1-3. RDS PostgreSQL作成
検索バー → `RDS` → 「データベースの作成」

| 項目 | 値 |
|---|---|
| エンジン | PostgreSQL |
| テンプレート | 開発/テスト |
| 可用性 | シングルDBインスタンス |
| DBインスタンス識別子 | `laravel-app-db` |
| マスターユーザー名 | `postgres` |
| 認証情報管理 | セルフマネージド |
| DBインスタンスクラス | db.t3.micro |
| ストレージ | gp2 / 20GB / 自動スケーリング無効 |
| VPC | デフォルトVPC |
| パブリックアクセス | **なし** |
| VPCセキュリティグループ | 新規作成: `laravel-app-rds-sg` |
| 最初のデータベース名 | `laravel_app` |
| バックアップ保持期間 | 0日 |
| マイナーバージョン自動アップグレード | 無効 |
| 削除保護 | 無効 |

作成後、エンドポイントをメモ: `laravel-app-db.xxxx.us-east-1.rds.amazonaws.com`

### 1-4. SSM Parameter Storeへの秘密情報登録
Systems Manager → 「パラメータストア」→「パラメータの作成」

```bash
# ローカルでAPP_KEYを確認
grep APP_KEY .env
```

| 名前 | タイプ | 値 |
|---|---|---|
| `/laravel-app/APP_KEY` | SecureString | `.env`のAPP_KEY値 |
| `/laravel-app/DB_PASSWORD` | SecureString | RDS作成時のパスワード |
| `/laravel-app/DB_HOST` | String | RDSエンドポイント |
| `/laravel-app/DB_DATABASE` | String | `laravel_app` |

KMSキーソース: `alias/aws/ssm`(デフォルト / 無料)

### 1-5. ecsTaskExecutionRoleへのSSM権限追加

> ⚠️ この手順を先に行わないとタスク起動時に「パラメータを取得できない」エラーになる

IAM → ロール → `ecsTaskExecutionRole` → 「許可を追加」→ `AmazonSSMReadOnlyAccess`をアタッチ

### 1-6. ECS Cluster作成
検索バー → `ECS` → 「クラスターの作成」

| 項目 | 値 |
|---|---|
| クラスター名 | `laravel-app-cluster` |
| インフラストラクチャ | AWS Fargate(サーバーレス)のみ |

### 1-7. ECS Task Definition作成
ECS → 「タスク定義」→「新しいタスク定義の作成」

**タスク設定:**

| 項目 | 値 |
|---|---|
| ファミリー名 | `laravel-app-task` |
| 起動タイプ | AWS Fargate |
| OS/アーキテクチャ | Linux/X86_64 |
| CPU | 0.5 vCPU |
| メモリ | 1 GB |
| タスク実行ロール | 新しいロールの作成(ecsTaskExecutionRole) |

**コンテナ設定:**

| 項目 | 値 |
|---|---|
| コンテナ名 | `laravel-app` |
| イメージURI | `<AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest` |
| コンテナポート | 8000 / TCP |
| CloudWatch Logs | 有効 / ロググループ: `/ecs/laravel-app` |

**環境変数(ValueFrom = SSM ARN参照):**

| キー | 値 |
|---|---|
| `APP_KEY` | `arn:aws:ssm:us-east-1:<AccountID>:parameter/laravel-app/APP_KEY` |
| `DB_PASSWORD` | `arn:aws:ssm:us-east-1:<AccountID>:parameter/laravel-app/DB_PASSWORD` |
| `DB_HOST` | `arn:aws:ssm:us-east-1:<AccountID>:parameter/laravel-app/DB_HOST` |
| `DB_DATABASE` | `arn:aws:ssm:us-east-1:<AccountID>:parameter/laravel-app/DB_DATABASE` |

**環境変数(Value = 直接入力):**

| キー | 値 |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `pgsql` |
| `DB_PORT` | `5432` |
| `DB_USERNAME` | `postgres` |
| `LOG_CHANNEL` | `stderr` |

### 1-8. ECS Service作成
ECS → `laravel-app-cluster` → 「サービス」→「作成」

| 項目 | 値 |
|---|---|
| 起動タイプ | Fargate |
| タスク定義 | `laravel-app-task` |
| サービス名 | `laravel-app-service` |
| 必要なタスク数 | 1 |
| VPC | デフォルトVPC |
| サブネット | 全て選択 |
| セキュリティグループ | 新規作成: `laravel-app-ecs-sg` / TCP:8000 / 0.0.0.0/0 |
| パブリックIP | **オン** |
| ロードバランサー | なし |

### 1-9. RDS SGにECSからのアクセスを許可
VPC → セキュリティグループ → `laravel-app-rds-sg` → インバウンドルールを編集

| タイプ | ポート | ソース |
|---|---|---|
| PostgreSQL | 5432 | `laravel-app-ecs-sg` |

### 1-10. 動作確認
- タスクが`RUNNING`になったらタスクIDをクリック → パブリックIPを確認
- ブラウザで `http://<パブリックIP>:8000` にアクセス
- Fortifyのログイン画面 → ログインできることを確認

### 1-11. 作業終了
ECS Service → 必要なタスク数を **0** に更新

---

## トラブルシューティング

| エラー | 原因 | 解決 |
|---|---|---|
| EssentialContainerExited / 終了コード1 | RDS SGがECSからの5432通信を許可していない | 1-9のインバウンドルールを追加 |
| exec format error | Apple Siliconで`--platform linux/amd64`なしでビルドした | ビルドコマンドに`--platform linux/amd64`を追加 |
