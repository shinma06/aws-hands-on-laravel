# AWS Hands-on App

Laravel 13 + Inertia(Vue3 Composition API) + Fortify + Laravel Boost。
PostgreSQLをDBに使用し、Eloquentを直接利用する標準的なLaravel MVC構成(Repository/Service層は導入しない)。

## スタック

- PHP 8.5 / Laravel 13
- Inertia + Vue3(`<script setup lang="ts">`)
- Fortify(認証)/ Laravel Boost(AIガイドライン・MCP)
- PostgreSQL 18
- FrankenPHP(Phase 1はOctaneなしのclassic mode)

## ローカル起動手順

前提: Docker(Docker Desktop または OrbStack等)が起動していること。

```bash
cp .env.example .env   # 既に.envがある場合は不要
docker compose up -d --build
```

起動後、ブラウザで http://localhost:8000/login を開くとFortifyのログイン画面が表示される。

- `app`コンテナ起動時にエントリポイント(`docker/entrypoint.sh`)が`php artisan migrate --force`を自動実行する(Phase 1はシングルタスク運用のための簡易対応)。
- フロントエンド資産はDockerビルド時に`npm run build`済み。コード変更を反映する場合は`docker compose up -d --build`で再ビルドすること。

停止:

```bash
docker compose down       # コンテナのみ停止
docker compose down -v    # DBデータも含めて削除する場合
```

## AWSデプロイ前提条件(Phase 1: 最小デプロイ)

[aws_laravel_roadmap.md](../aws_laravel_roadmap.md)のPhase 1に対応。デプロイ前に以下を用意すること。

- AWSアカウント(リージョン: `ap-northeast-1`)、コストガードレール(Budgets等)設定済み
- ECRリポジトリ(本Dockerfileのイメージをpush)
- RDS PostgreSQL(18系)インスタンス
- ECS Cluster(Fargate)・Task Definition・Service(Phase 1はALBなし、パブリックサブネット直結)
- DB接続情報・`APP_KEY`等はSecrets Manager or SSM Parameter Store経由で環境変数として注入する
- `.env.example`の`AWS_*`(S3)はPhase 1以降、`REDIS_*`はPhase 4以降、`BEDROCK_*`はPhase 5以降で使用する
