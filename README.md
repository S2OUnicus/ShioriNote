![しおりノート brand](public/brand.png)

# しおりノート（ShioriNote）

**しおりノート（ShioriNote）** は、本ごとの目次に沿って読書の進み具合を記録できる、可愛い雰囲気の読書進展管理サイトです。章・節・小節単位で進展をチェックし、読了率、章ごとの完成度、達成記録、マインドマップ、日別・時間別の進み具合をグラフで確認できます。

> GitHub description: 目次単位で本の読書進展を記録し、EChartsで可視化するPHP製の読書管理サイト。

## イメージ

![しおりノート v2.0.0 イメージ](docs/images/v2.0.0.png)

## 主な機能

- ログイン必須の読書進展管理サイト
- 図書リスト、図書紹介ページ、読書進展ページ
- 章・節・小節から選べる進展管理単位
- 本ごとに選べる進展時間粒度（日別・時間別）
- 本ごとに選べるマインドマップ詳細度（章まで・節まで・小節まで）
- 本ごとに選べる章ごと完成度チャート初期表示（Rounded Bar / Horizontal Bar）
- チェックボックスと整数入力による進展記録
- HTMXによる自動保存
- 目次ごとの一行メモ、完成時間、メモ編集モーダル
- 全体進展バー、章ごと完成度（Rounded / Horizontal切替）、章ごと残り、進展まとめグラフ
- 章・節・小節まで詳細度を選べるEChartsツリー型マインドマップ
- CSPに配慮したチャート拡大プレビュー、章タブ、達成記録テーブル
- ユーザーセンター、パスワード変更、個人情報編集
- 管理システムによる図書管理、目次導入、ユーザー管理、サイト基本設定、ユーザー別進展管理、メモまとめ管理
- 初回インストーラーによる設定ファイル・データベース・初期管理ユーザー作成
- `public/` をWebルートにする安全寄りの構成

## 必要環境

- PHP 8.1 以上（PHP 8.5 以上推奨）
- MySQL / MariaDB
- Nginx または Apache
- PHP拡張：PDO MySQL、mbstring、fileinfo、openssl

## インストール

### 1. ファイルを配置する

サーバー上の任意の場所にファイルを置き、Webルートを必ず `public/` に向けます。

```text
/var/www/shiorinote/public
```

`config/`、`page/`、`database-sample/` などを直接Web公開しないでください。

### 2. 空のデータベースを作成する

インストーラーはテーブル作成と初期データ導入を自動で行いますが、使用するデータベース自体は先に作成してください。

```bash
mysql -u root -p -e "CREATE DATABASE shiorinote CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
```

SQLファイル内ではデータベース名を固定していません。`shiorinote` 以外の任意のデータベース名でも使えます。

### 3. ブラウザで初期設定する

サイトURLへアクセスします。

```text
https://yourdomain.com/
```

`install.lock` が存在しない場合、自動で以下へ移動します。

```text
https://yourdomain.com/install/
```

インストーラーでは、以下を順番に設定します。

1. セットアップ環境チェック
2. データベース設定
3. サイト基本設定
4. 初期アカウントと管理パス
5. データベースインストール
6. 完了画面

通常の初期アカウント権限は **総管理** です。開発者権限の初期アカウントを作る場合は、初回のみ以下にアクセスします。

```text
https://yourdomain.com/install/s2odev
```

インストールが完了すると、プロジェクトルートに `install.lock` が作成され、再インストールはできなくなります。

## インストーラーで作られるもの

- `config/base.inc.phtml`
- `install.lock`
- `update.lock`
- 初期データベーステーブル
- 初期サイト設定
- 初期管理ユーザー

既に `config/base.inc.phtml` が存在する場合、インストーラーは次のような名前でバックアップします。

```text
config/base.inc.phtml-260624161709.bak
```

これらのファイルは `.gitignore` に入っており、GitHubへ公開しない想定です。

## 手動SQLについて

`database-sample/` には、手動導入や確認用のSQLも入っています。

```text
database-sample/01_schema.sql
database-sample/02_seed.sample.sql
database-sample/03_sample_book.sql
```

`02_seed.sample.sql` はサイト初期設定のみを登録します。**初期ユーザーは登録しません。** 初期管理ユーザーは `/install/` で作成してください。

テーブルプレフィックスを使う場合は、インストーラーの「テーブルプレフィックス」欄で指定します。初期値は `sn1_` です。

## 目次ファイルの形式

管理システムから `.txt` または `.md` の目次ファイルを導入できます。

```text
はじめに
    読書の準備
        メモの考え方
    進展管理
実践
    毎日の記録
    完結まで
```

目次ファイルには、章番号・節番号を付けず、タイトルだけを書きます。画面では「第 1 章」「第 1 節」「第 1 小節」のように自動採番して表示します。

## 管理システムについて

管理システムのURLパスは、インストール時に指定します。初期値はランダム16桁の小文字英数字です。管理パスは重要なので、完了画面で必ず保存してください。

管理システムでは以下を設定できます。

- サイト基本管理
- ユーザー管理
- 図書管理
- ユーザー別進展管理
- メモまとめ管理
- その他

新規登録機能は初期値OFFです。必要な場合だけ管理システムでONにしてください。

## GitHub公開時の注意

以下は公開しない想定です。

- `config/base.inc.phtml`
- `config/base.inc.phtml-*.bak`
- `install.lock`
- `update.lock`
- `backup/database/*.sql`
- `database/`
- `public/.user.ini`
- 実運用中のアップロード画像
- 個人情報や本番DBのダンプ

公開用のサンプルとして、以下を同梱しています。

- `config/base.sample.phtml`
- `database-sample/`
- `public/upload/avatar/default-avatar.svg`
- `public/upload/book/default-cover.svg`

## 既存環境をv2.4.0へ更新する場合

既存環境で既に `config/base.inc.phtml` とDBがある場合、通常はインストーラーを使わず、ファイルを差し替えてからCLIアップグレードツールを実行してください。

```bash
php tool/upgrade/index.php
```

このツールは、先に現在のデータベースを `backup/database/` へSQL形式でバックアップしてから、`database-sample/` 内の必要な `migration_v*.sql` を順番に実行します。完了すると、プロジェクトルートに `update.lock` を作成・更新し、DB更新済みバージョンを記録します。

`v2.4.0` 自体のテーブル構造変更はありませんが、`v2.3.0` 未適用の環境では、ツールが `migration_v2.3.0.sql` を実行します。`migration_v2.3.0.sql` は、PHPMyAdminで出やすい `ADD COLUMN IF NOT EXISTS` の構文エラーを避ける形式に修正済みです。

既存DBが無プレフィックスの場合は、`config/base.inc.phtml` の `db.prefix` を空文字にしてください。プレフィックスを使っている場合、手動でSQLを流すより、必ず上記CLIツールを使う方が安全です。

### アップグレードツールの安全動作

- Webブラウザからは実行できません。CLI専用です。
- 実行前にDB接続を確認します。
- 実行前に `backup/database/YYYYmmdd_HHiiss_ShioriNote.sql` を作成します。
- `update.lock` のバージョン番号を見て、必要なmigrationだけ実行します。
- SQLファイルは `database-sample/` 内の `migration_v*.sql` だけを対象にします。
- 実行エラー時は処理を中断し、エラー内容を表示します。

## ライセンス

このプロジェクトは **Creative Commons Attribution-ShareAlike 4.0 International（CC BY-SA 4.0）** で公開します。

- English: `LICENSE`
- Japanese: `LICENSE.ja`

## バージョン

現在のバージョン：`v2.4.0`

変更履歴は `CHANGELOG.md` を確認してください。
