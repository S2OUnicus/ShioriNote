![しおりノート brand](public/brand.png)

# しおりノート（ShioriNote）

**しおりノート（ShioriNote）** は、本ごとの目次に沿って読書の進み具合を記録できる、可愛い雰囲気の読書進展管理サイトです。章・節・小節単位で進展をチェックし、読了率や日別・時間別の進み具合をグラフで確認できます。ユーザーごとに開始時間、完結時間、メモを保存できるので、個人の読書記録にも、少人数の読書会にも使えます。

GitHub description:

> 目次単位で本の読書進展を記録し、EChartsで可視化するPHP製の読書管理サイト。

English description:

> ShioriNote is a PHP-based reading progress tracker that records book progress by table of contents, supports notes and completion dates, and visualizes progress with ECharts.

## 主な機能

- ログイン必須の読書進展管理サイト
- 図書リスト、図書紹介ページ、読書進展ページ
- 章・節・小節から選べる進展管理単位
- チェックボックスと整数入力による進展記録
- 読書進展の自動保存（HTMX対応）
- 目次ごとの自由メモ
- 全体進展バー、章ごとの進展グラフ、日別・時間別進展グラフ
- 総進展100%時の「完結」表示と完結時間の自動記録
- ユーザーごとのTimezone設定
- ユーザーセンター、パスワード変更、個人情報編集
- 管理システムによる図書管理、目次導入、ユーザー管理、サイト基本設定
- 新規登録ON/OFF設定（初期値はOFF）
- `public/` をWebルートにする安全寄りの構成

## 画面の流れ

通常ユーザーは、以下の順番で使います。

```text
ログイン → 図書リスト → 図書紹介 → 読書進展管理
```

管理者は管理システムから、図書の追加、目次ファイルの導入、ユーザー権限の変更、サイト基本設定を行います。

## 必要環境

- PHP 8.5 以上
- MySQL 9 以上
- Nginx 1.31.1 以上
- PHP拡張：PDO MySQL、mbstring、fileinfo

## インストール

### 1. ファイルを配置する

サーバー上の任意の場所にファイルを置き、NginxのWebルートを必ず `public/` に向けます。

例：

```text
/var/www/shiorinote/public
```

`config/`、`page/`、`database-sample/` などを直接Web公開しないでください。

### 2. 設定ファイルを作る

`config/base.sample.phtml` をコピーして、実運用用の設定ファイルを作ります。

```bash
cp config/base.sample.phtml config/base.inc.phtml
```

その後、`config/base.inc.phtml` の以下を変更してください。

- データベース名
- データベースユーザー名
- データベースパスワード
- 管理システムのURLパス
- サイト名、author、theme-color
- 初期Timezone

`config/base.inc.phtml` は `.gitignore` に入っています。GitHubへ公開しないでください。

### 3. データベースを作る

初回インストールでは、サンプルSQLを参考にデータベースを作成します。

```bash
mysql -u root -p < database-sample/schema.sql
mysql -u root -p < database-sample/seed.sample.sql
```

必要ならサンプル図書も導入できます。

```bash
mysql -u root -p < database-sample/sample_book.sql
```

`seed.sample.sql` にはローカル確認用のサンプル管理者アカウントが入っています。実運用では、ログイン後すぐにID、メール、パスワード、管理パスを変更してください。

### 4. Nginxを設定する

`deploy/nginx-shiorinote.conf` を参考にしてください。

重要な点は以下です。

```nginx
root /var/www/shiorinote/public;
try_files $uri $uri/ /index.php?$query_string;
```

`public/` 以外をWebルートにしないでください。

## 管理システムについて

管理システムのURLパスは、`config/base.inc.phtml` の `admin_panel_path` で定義します。

サンプル値はランダムな文字列になっています。実運用では、さらに自分用の長いランダム文字列へ変更することをおすすめします。

管理システムでは以下を設定できます。

- サイト基本管理
- ユーザー管理
- 図書管理
- ユーザー別進展管理
- その他

新規登録機能は初期値OFFです。必要な場合だけ、管理システムでONにしてください。

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

目次ファイルには、章番号・節番号を付けず、タイトルだけを書きます。インデントはスペースまたはタブに対応しています。画面では「第 1 章」「第 1 節」「第 1 小節」のように自動採番して表示します。図書ごとに進展管理単位を「章」「節」「小節」から選べます。

## GitHub公開時の注意

以下は公開しない想定です。

- `config/base.inc.phtml`
- `database/`
- `public/.user.ini`
- 実運用中のアップロード画像
- 個人情報や本番DBのダンプ

公開用のサンプルとして、以下を同梱しています。

- `config/base.sample.phtml`
- `database-sample/`
- `public/upload/avatar/default-avatar.svg`
- `public/upload/book/default-cover.svg`

## ライセンス

このプロジェクトは **Creative Commons Attribution-ShareAlike 4.0 International（CC BY-SA 4.0）** で公開します。

- English: `LICENSE`
- Japanese: `LICENSE.ja`

## バージョン

現在のバージョン：`v1.0.2`

変更履歴は `CHANGELOG.md` を確認してください。
