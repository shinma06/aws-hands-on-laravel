# AWS学習ロードマップ(最新版)

**作業リージョン**: us-east-1(バージニア北部)  
**アプリ構成**: Laravel 13 + Vue3(Inertia) + FrankenPHP + Laravel Octane  
**DB**: Aurora PostgreSQL Serverless v2(Writer + Reader)  
**最終更新**: 2026-06-20

---

## 役割分担

| 領域 | 担当 |
|---|---|
| ローカルアプリ構築・コード変更 | **Claude Code** |
| AWSリソースの作成・設定操作 | **あなた**(各Phaseの手順書に沿って実行) |
| 設計判断・コスト計算・トラブルシュート | **このチャット** |

---

## フェーズ一覧

| Phase | 内容 | 主なAWSサービス | 状態 |
|---|---|---|---|
| 0 | アカウント基盤・コストガードレール | IAM, Budgets, AWS CLI | ✅ 完了 |
| 1 | 最小デプロイ(FrankenPHP classic / パブリックサブネット直結) | ECR, ECS(Fargate), SSM | ✅ 完了 |
| 2 | Octane化(FrankenPHP worker mode) | ECR, ECS | ✅ 完了 |
| 3 | プライベートサブネット + NAT Gateway + ALB | VPC, ALB, NAT Gateway | ✅ 完了 |
| EX | Aurora Serverless v2(ボーナス学習) | Aurora PostgreSQL | ✅ 完了 |
| 4 | Redis組み込み | ElastiCache or サイドカー | 未 |
| 5 | AI機能(固定プロンプト) | Bedrock Runtime | 未 |
| 6 | メール機能 | SES | 未 |

---

## 現在の構成(Phase EX完了時点)

```
Internet
    ↓ HTTP:80
  ALB (laravel-app-alb) [Public subnet × 2AZ / us-east-1a, 1b]
    ↓ HTTP:8000
  ECS Task (Fargate / Laravel Octane + FrankenPHP) [Private subnet]
    ↓ 外向き通信(ECR pull / CloudWatch / SSM) ← NAT Gateway経由(作業時のみ起動)
    ↓ TCP:5432
  Aurora PostgreSQL Serverless v2 [Private subnet]
  (Writer endpoint / Reader endpoint)
```

---

## AWSリソース一覧(現時点)

| リソース | 名前/識別子 | 備考 |
|---|---|---|
| VPC | `laravel-app-vpc` (10.0.0.0/16) | |
| Public subnet | `laravel-app-subnet-public1/2-us-east-1a/b` | ALB配置 |
| Private subnet | `laravel-app-subnet-private1/2-us-east-1a/b` | ECS/Aurora配置 |
| IGW | `laravel-app-igw` | |
| NAT Gateway | ※作業時のみ起動・作業後削除 | |
| SG(ALB) | `laravel-app-alb-sg` | HTTP:80 / 0.0.0.0/0 |
| SG(ECS) | `laravel-app-ecs-sg-new` | TCP:8000 / ALB SGのみ |
| SG(DB) | `laravel-app-rds-sg-new` | TCP:5432 / ECS SGのみ |
| ECR | `laravel-app` | |
| ECS Cluster | `laravel-app-cluster` | |
| ECS Task Definition | `laravel-app-task` | |
| ECS Service | `laravel-app-service` | |
| ALB | `laravel-app-alb` | HTTP:80 |
| Target Group | `laravel-app-tg` | HTTP:8000 / ヘルスチェック: / (200-302) |
| DB Subnet Group | `laravel-app-db-subnet-group` | |
| Aurora Cluster | `laravel-app-aurora-cluster` | Serverless v2 / 最小ACU:0 / 最大ACU:1 |

---

## SSMパラメータ一覧

| パラメータ名 | タイプ | 内容 |
|---|---|---|
| `/laravel-app/APP_KEY` | SecureString | LaravelアプリキーL |
| `/laravel-app/DB_PASSWORD` | SecureString | Auroraマスターパスワード |
| `/laravel-app/DB_HOST` | String | AuroraWriterエンドポイント |
| `/laravel-app/DB_DATABASE` | String | `laravel_app_aurora` |
| `/laravel-app/DB_READ_HOST` | String | AuroraReaderエンドポイント |

---

## コスト管理ルール

### 作業終了時(毎回必須)
1. ECS Service → 必要なタスク数を **0** に更新
2. NAT Gateway → **削除**(Elastic IPも解放)

### 次回作業開始時
1. NAT Gateway を再作成(パブリックサブネット / Elastic IP新規割り当て)
2. プライベートサブネットのルートテーブルを確認・更新(`0.0.0.0/0` → 新NAT GatewayのID)
3. ECS Service → 必要なタスク数を **1** に更新

### 常時起動リソース(課金継続中)
- ALB: 約$0.016/時間 + LCU課金
- Aurora: 最小ACU=0のため自動pause時はストレージのみ(月$1〜2程度)

---

## 各フェーズの手順書

- [Phase 0: アカウント基盤](./phase_0_account_setup.md)
- [Phase 1: 最小デプロイ](./phase_1_minimal_deploy.md)
- [Phase 2: Octane化](./phase_2_octane.md)
- [Phase 3: プライベートサブネット + ALB](./phase_3_private_subnet_alb.md)
- [Phase EX: Aurora Serverless v2](./phase_ex_aurora.md)
- [Phase 4: Redis](./phase_4_redis.md)
- [Phase 5: Bedrock](./phase_5_bedrock.md)
- [Phase 6: SES](./phase_6_ses.md)
