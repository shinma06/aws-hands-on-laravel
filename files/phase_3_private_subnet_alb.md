# Phase 3: プライベートサブネット + NAT Gateway + ALB

**ステータス**: ✅ 完了  
**所要時間**: 約90分

---

## 目的
ECS TaskをパブリックIPなしのプライベートサブネットに移動し、ALB経由でのアクセスに切り替える。本番に近いセキュアなネットワーク構成を学ぶ。

---

## 完成構成

```
Internet
    ↓ HTTP:80
  ALB (laravel-app-alb) [Public subnet × 2AZ]
    ↓ HTTP:8000 (SGで制御)
  ECS Task (Fargate) [Private subnet]
    ↓ 外向き通信(ECR/CloudWatch/SSM)
  NAT Gateway [Public subnet] → IGW → Internet
    ↓ TCP:5432
  Aurora PostgreSQL [Private subnet]
```

---

## セキュリティグループ設計

| SG名 | 用途 | インバウンドルール |
|---|---|---|
| `laravel-app-alb-sg` | ALB | HTTP:80 / 0.0.0.0/0 |
| `laravel-app-ecs-sg-new` | ECS Task | TCP:8000 / `laravel-app-alb-sg`のみ |
| `laravel-app-rds-sg-new` | Aurora | TCP:5432 / `laravel-app-ecs-sg-new`のみ |

---

## 手順

### 3-1. VPC作成
VPC → 「VPCを作成」→「VPCなど(VPC and more)」を選択

| 項目 | 値 |
|---|---|
| 名前タグ | `laravel-app` |
| IPv4 CIDR | `10.0.0.0/16` |
| AZ数 | 2 |
| パブリックサブネット数 | 2 |
| プライベートサブネット数 | 2 |
| NATゲートウェイ | **リージョナル - 新規**(1個で全AZをカバー) |
| VPCエンドポイント | なし |

### 3-2. セキュリティグループの作成(3つ)

#### ALB用
| 項目 | 値 |
|---|---|
| 名前 | `laravel-app-alb-sg` |
| VPC | `laravel-app-vpc` |
| インバウンド | HTTP:80 / 0.0.0.0/0 |

#### ECS用
| 項目 | 値 |
|---|---|
| 名前 | `laravel-app-ecs-sg-new` |
| VPC | `laravel-app-vpc` |
| インバウンド | TCP:8000 / `laravel-app-alb-sg` |

#### Aurora用
| 項目 | 値 |
|---|---|
| 名前 | `laravel-app-rds-sg-new` |
| VPC | `laravel-app-vpc` |
| インバウンド | PostgreSQL:5432 / `laravel-app-ecs-sg-new` |

### 3-3. Aurora用DBサブネットグループ作成
RDS → 「サブネットグループ」→「DBサブネットグループを作成」

| 項目 | 値 |
|---|---|
| 名前 | `laravel-app-db-subnet-group` |
| VPC | `laravel-app-vpc` |
| サブネット | プライベートサブネット2つ(`private`が名前に含まれるもの) |

### 3-4. ターゲットグループ作成
EC2 → 「ターゲットグループ」→「ターゲットグループの作成」

| 項目 | 値 |
|---|---|
| ターゲットタイプ | **IPアドレス**(Fargateはこれ) |
| 名前 | `laravel-app-tg` |
| プロトコル/ポート | HTTP / 8000 |
| VPC | `laravel-app-vpc` |
| ヘルスチェックパス | `/` |
| 成功コード | **200-302**(Fortifyのトップが302リダイレクトするため) |

ターゲットの登録は何もせず「作成」(ECSが自動登録する)

### 3-5. ALB作成
EC2 → 「ロードバランサー」→「ロードバランサーの作成」→「Application Load Balancer」

| 項目 | 値 |
|---|---|
| 名前 | `laravel-app-alb` |
| スキーム | インターネット向け |
| VPC | `laravel-app-vpc` |
| サブネット | パブリックサブネット2つ |
| セキュリティグループ | `laravel-app-alb-sg` |
| リスナー | HTTP:80 → `laravel-app-tg`に転送 |

作成後、**DNS名をメモ**(例: `laravel-app-alb-xxxx.us-east-1.elb.amazonaws.com`)

### 3-6. ECS Service新規作成(旧Serviceを削除してから)
旧`laravel-app-service`を削除 → 新規作成

| 項目 | 値 |
|---|---|
| 起動タイプ | Fargate |
| タスク定義 | `laravel-app-task`(最新リビジョン) |
| サービス名 | `laravel-app-service` |
| 必要なタスク数 | 1 |
| VPC | `laravel-app-vpc` |
| サブネット | **プライベートサブネット2つ** |
| セキュリティグループ | `laravel-app-ecs-sg-new` |
| パブリックIP | **オフ** |
| ロードバランサー | `laravel-app-alb` / `laravel-app-tg` |

### 3-7. 動作確認
`http://<ALBのDNS名>` でブラウザアクセス → ログイン確認

### 3-8. 作業終了時の課金停止(毎回必須)
1. ECS Service → 必要なタスク数を **0** に更新
2. VPC → NAT Gatewayを **削除**

---

## 次回作業開始時の手順

```
1. NAT Gatewayを再作成
   - サブネット: laravel-app-subnet-public1-us-east-1a
   - Elastic IP: 新規割り当て

2. プライベートサブネットのルートテーブルを更新
   - laravel-app-rtb-private1/2 の 0.0.0.0/0 を新NAT GatewayのIDに変更

3. ECS Service → 必要なタスク数を 1 に更新
```

---

## トラブルシューティング

| エラー | 原因 | 解決 |
|---|---|---|
| SSMへの接続エラー(ResourceInitializationError) | NAT Gatewayがない / ルートテーブルが古いIDを向いている | NAT Gatewayを再作成してルートテーブルを更新 |
| CloudFormation DELETE_FAILED | 旧SGに依存オブジェクトが残っている | 該当SGのインバウンド/アウトバウンドルールを全削除してから「削除を再試行」 |
| ECS ServiceのVPC変更不可 | ECS ServiceはVPCを作成後に変更できない | Serviceを削除して新規作成 |
| RDSのVPC変更不可 | RDSはVPCを作成後に変更できない | RDSを削除して新VPCで再作成 |
