# Phase EX: Aurora Serverless v2(ボーナス学習)

**ステータス**: ✅ 完了  
**所要時間**: 約60分

---

## 目的
RDS PostgreSQL(単一エンドポイント)からAurora Serverless v2(Writer + Reader構成)に移行し、AuroraのWriter/Readerエンドポイント分離とLaravelのread/write設定を学ぶ。

---

## RDS PostgreSQL vs Aurora Serverless v2

| 項目 | RDS PostgreSQL | Aurora Serverless v2 |
|---|---|---|
| エンドポイント | 1つ | Writer/Readerの2つ |
| スケーリング | インスタンスサイズ固定 | ACU単位で自動スケール |
| アイドル時コスト | db.t3.microで常時課金(月$15前後) | 最小ACU=0で自動pause → ストレージのみ(月$1〜2) |
| フェイルオーバー | 数分 | 通常30秒以内でReaderがWriterに昇格 |
| Laravel設定 | DB_HOSTのみ | read/write分離 + sticky設定 |

---

## Aurora クラスター作成

RDS → 「データベースの作成」

| 項目 | 値 |
|---|---|
| エンジン | Aurora (PostgreSQL Compatible) |
| 作成方法 | フル設定 |
| テンプレート | 開発/テスト |
| スケーラビリティタイプ | Serverless v2 |
| 最小ACU | **0**(自動pause有効) |
| 最大ACU | **1** |
| 非アクティブ後に一時停止 | 5分 |
| DBクラスター識別子 | `laravel-app-aurora-cluster` |
| マスターユーザー名 | `postgres` |
| 認証情報管理 | セルフマネージド |
| クラスターストレージ設定 | **Auroraスタンダード** |
| 可用性と耐久性 | **別のAZでAuroraレプリカ/リーダーノードを作成する** |
| VPC | `laravel-app-vpc` |
| DBサブネットグループ | `laravel-app-db-subnet-group` |
| パブリックアクセス | なし |
| VPCセキュリティグループ | `laravel-app-rds-sg-new` |
| モニタリング | Performance Insights/拡張モニタリング無効 |
| 削除保護 | 無効 |

作成後に以下の2つのエンドポイントをメモ:
- **クラスターエンドポイント**(Writer): `laravel-app-aurora-cluster.cluster-xxxx.us-east-1.rds.amazonaws.com`
- **リーダーエンドポイント**(Reader): `laravel-app-aurora-cluster.cluster-ro-xxxx.us-east-1.rds.amazonaws.com`

---

## SSMパラメータの更新・追加

| 操作 | パラメータ名 | 値 |
|---|---|---|
| 更新 | `/laravel-app/DB_HOST` | Writerエンドポイント |
| 更新 | `/laravel-app/DB_DATABASE` | `laravel_app_aurora` |
| 新規追加 | `/laravel-app/DB_READ_HOST` | Readerエンドポイント |

---

## ローカル作業(Claude Code)

### config/database.phpの変更(read/write分離)

```php
'pgsql' => [
    'driver' => 'pgsql',
    'read' => [
        // ReaderエンドポイントへフォールバックはDB_HOSTを使用(ローカル互換)
        'host' => env('DB_READ_HOST', env('DB_HOST')),
    ],
    'write' => [
        'host' => env('DB_HOST'),
    ],
    // 書き込み直後の読み取りはWriterから行う(データ不整合を防ぐ)
    'sticky' => true,
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
],
```

---

## ECS Task Definitionの更新

ECS → 「タスク定義」→ `laravel-app-task` → 「新しいリビジョンを作成」

追加する環境変数:

| キー | タイプ | 値 |
|---|---|---|
| `DB_READ_HOST` | ValueFrom | `arn:aws:ssm:us-east-1:<AccountID>:parameter/laravel-app/DB_READ_HOST` |

---

## デプロイ手順

```bash
docker build --platform linux/amd64 -t laravel-app .
docker tag laravel-app:latest \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest
docker push \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest
```

ECS → `laravel-app-service` → 「更新」→ 必要なタスク数:1 / 「強制的に新しいデプロイ」にチェック → 「更新」

---

## 動作確認

- ALBのDNS名でアクセス → ログイン確認(Readerエンドポイントからの読み取り)
- 新規ユーザー登録(Writerエンドポイントへの書き込み)

---

## 注意点

- **Auroraのcold start**: 最小ACU=0のため、初回接続時にAuroraが起動するまで約15秒かかる。`php artisan migrate`がタイムアウトしてタスクが`STOPPED`になる場合は再デプロイする(2回目以降はAuroraが起動済みで成功する)
- **sticky: true**: 書き込み直後に同じリクエスト内で読み取りが発生した場合、Readerではなく書き込んだWriterから読み取ることでデータの不整合を防ぐ
