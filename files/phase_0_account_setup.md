# Phase 0: アカウント基盤・コストガードレール

**ステータス**: ✅ 完了  
**所要時間**: 約30分

---

## 目的
AWSアカウントを安全・低コストで使うための基盤を整える。以降のPhaseで使う全リソースの土台になる。

---

## 実施内容

### 0-1. ルートユーザーのMFA設定
1. ルートユーザーでAWSマネジメントコンソールにログイン
2. 右上アカウント名 → 「セキュリティ認証情報」
3. 「多要素認証(MFA)」→「MFAデバイスを割り当て」→ パスキーを登録
4. **ルートユーザーのアクセスキーは作成しない(以後ルートは使わない)**

### 0-2. プラン確認
- Billing and Cost Management → ホーム画面の「コストと使用状況」ウィジェットで確認
- 「Upgrade plan」ボタンがなければPaid Plan
- 今回はus-east-1で作業継続(ap-northeast-1への移行は学習コストに見合わないため見送り)

### 0-3. AWS Budgets(コストアラート)
Billing → 「予算」→「予算を作成」で以下の3つを作成:

| 予算名 | 予算額 | アラートしきい値 |
|---|---|---|
| monthly-guardrail-20 | $20 | 実際のコスト 80% |
| monthly-guardrail-50 | $50 | 実際のコスト 80% |
| monthly-guardrail-100 | $100 | 実際のコスト 80% |

**各予算の共通設定:**
- タイプ: コスト予算 / 期間: 月次 / 更新タイプ: 定期予算
- 予算設定方法: 固定 / 範囲: すべてのAWSサービス
- 通知先: 普段確認するメールアドレス

### 0-4. IAM管理ユーザーの作成
IAM → 「ユーザー」→「ユーザーを作成」

| 項目 | 値 |
|---|---|
| ユーザー名 | `dev-admin` |
| コンソールアクセス | 有効 / Custom password |
| パスワードリセット強制 | なし |
| アタッチするポリシー | `AdministratorAccess`(直接アタッチ) |

> ⚠️ AdministratorAccessは学習用単独アカウントのための簡略化。複数人・本番環境では使用しない。

作成後:
- 「セキュリティ認証情報」タブ → MFAデバイスを割り当て(パスキー)
- 「アクセスキーを作成」→ ユースケース: CLI → **Access Key IDとSecret Access Keyをコピーして保存**

### 0-5. ローカルAWS CLI設定

```bash
aws configure
# AWS Access Key ID: (発行したキー)
# AWS Secret Access Key: (発行したシークレット)
# Default region name: us-east-1
# Default output format: json

# 確認
aws sts get-caller-identity
# "Arn": "arn:aws:iam::<AccountID>:user/dev-admin" が返ればOK
```

---

## トラブルシューティング
- プランが不明な場合: Billing → Free Tier画面に「残り◯日」が出ればFree Plan / 出なければPaid Plan
