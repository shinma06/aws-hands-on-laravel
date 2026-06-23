# Phase 2: Octane化(FrankenPHP worker mode)

**ステータス**: ✅ 完了  
**所要時間**: 約30分

---

## 目的
FrankenPHP classic mode(1リクエスト=都度ブート)からOctane worker mode(常駐プロセスでリクエストを処理)に切り替え、パフォーマンスを向上させる。

---

## Phase 1からの差分

| ファイル | 変更内容 |
|---|---|
| `docker/entrypoint.sh` | `exec docker-php-entrypoint "$@"` → `exec php artisan octane:frankenphp ...` |
| `Dockerfile` | Stage 2末尾の`CMD`行を削除(Octaneが自前でCaddyfileを管理するため) |
| `docker-compose.yml` | `OCTANE_HTTPS: "false"` を環境変数に追加 |

---

## ローカル作業(Claude Code)

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp
# バイナリのダウンロードを聞かれた場合は「no」(Dockerイメージに含まれているため)

docker compose build --no-cache
docker compose up
# http://localhost:8000 でFortifyのログイン画面が表示されることを確認
```

### docker/entrypoint.sh 最終形

```sh
#!/bin/sh
set -e

# マイグレーション実行(シングルタスク運用前提)
php artisan migrate --force

# Octane worker modeで起動
exec php artisan octane:frankenphp \
    --workers=1 \
    --max-requests=500 \
    --port=8000 \
    --host=0.0.0.0 \
    --log-level=info
```

> ⚠️ `--workers=1`: ECS 0.5 vCPUでは1固定。増やすとOOMリスクあり  
> ⚠️ `--max-requests=500`: 500リクエスト処理後にworkerを再起動しメモリリークを防ぐ  
> ⚠️ `--host=0.0.0.0`: Dockerコンテナ外からアクセスするために必須

---

## AWSデプロイ手順

```bash
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com

docker build --platform linux/amd64 -t laravel-app .

docker tag laravel-app:latest \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest
docker push \
  <AccountID>.dkr.ecr.us-east-1.amazonaws.com/laravel-app:latest
```

ECS → `laravel-app-service` → 「更新」→「強制的に新しいデプロイ」にチェック → 「更新」

---

## 正常起動の確認ログ

```json
{"logger":"frankenphp","msg":"FrankenPHP started 🐘","php_version":"8.5.7","num_threads":4}
{"msg":"using config from file","file":"/app/vendor/laravel/octane/src/Commands/stubs/Caddyfile"}
```

## 無視してよいwarning

```
"msg":"HTTP/2 skipped because it requires TLS"
"msg":"HTTP/3 skipped because it requires TLS"
```
HTTPSでないため出るwarning。Phase 3でALB + TLSを追加した時点で解消する。

---

## Octane特有の注意点

1. **staticプロパティにリクエスト固有の状態を持つと次のリクエストに引き継がれる** → `max_requests`で定期再起動して回避
2. シングルトンにリクエスト固有データを持たせない
3. `config/octane.php` の`warm`リストでサービスプロバイダーのウォームアップを管理できる
