#!/bin/sh
set -e

# Phase 1はシングルタスク運用のため、起動時にマイグレーションを直接実行する
# (タスクが複数並行起動するフェーズでは、別途リリースステップとして分離すること)
php artisan migrate --force

exec php artisan octane:frankenphp \
    --workers=1 \
    --max-requests=500 \
    --port=8000 \
    --host=0.0.0.0 \
    --log-level=info
