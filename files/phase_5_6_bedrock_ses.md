# Phase 5: Bedrock(AI機能)

**ステータス**: 未着手

---

## 目的
AWS BedrockのRuntimeAPIを使い、固定プロンプトでテキストを受け取るシンプルなAI機能をLaravelに組み込む。

## 主な手順(予定)
1. Bedrockコンソールでモデルアクセスを有効化(例: Claude Sonnet)
2. ECS Task RoleにBedrockの`InvokeModel`権限を付与
3. AWS SDK for PHP経由で固定プロンプト呼び出し
4. ボーナスクレジット用5タスクの「Bedrockでプロンプトをテスト」もここで完了

---

*(Phase開始時に詳細手順を追記)*

---

# Phase 6: SES(メール機能)

**ステータス**: 未着手

---

## 目的
Amazon SESを使ってFortifyの認証メール・通知メールをAWS経由で送信できるようにする。

## 主な手順(予定)
1. SES Sandboxモードで送信元メールアドレスを検証
2. LaravelのMAIL_MAILERをSES(`ses`)に変更
3. ECS Task RoleにSESの`SendEmail`権限を付与
4. Fortifyのメール認証・パスワードリセットメールが届くことを確認

> Sandboxモード: 検証済みメールアドレス間のみ送信可能 / 実質無料

---

*(Phase開始時に詳細手順を追記)*
