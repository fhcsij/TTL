# TwoTimeLight 部署說明

這份資料夾是從原本 XAMPP 專案整理出的部署版本。PHP/MySQL 主機可以直接使用；GitHub Pages、Vercel 靜態部署不能直接跑這個專案，因為它需要 PHP 與 MySQL。

## 部署步驟

1. 在主機後台建立 MySQL 資料庫。
2. 進入主機提供的 phpMyAdmin，選取剛建立的資料庫，匯入本機保留的 `twotimelight.sql`。基於資料保護，這個 SQL dump 不會提交到公開 GitHub repo。
3. 複製 `php/config.local.example.php` 成 `php/config.local.php`。
4. 在 `php/config.local.php` 填入主機提供的資料庫主機、帳號、密碼、資料庫名稱。
5. 將本資料夾內所有網站檔案上傳到主機網站根目錄，例如 `htdocs` 或 `public_html`。
6. 確認 `Image/uploads`、`Image/uploads/products` 與 `var/sessions` 可寫入，否則上傳圖片或登入狀態可能會失敗。

## 本機測試

如果仍在 XAMPP 測試，可以不用建立 `php/config.local.php`；預設會連到：

```php
localhost / root / 空密碼 / twotimelight
```

若本機資料庫設定不同，也可以建立 `php/config.local.php` 覆蓋預設值。

## Vercel 部署注意

根目錄的 `vercel.json` 會把網站公開路徑導到 `TTL-deploy`，並把 `/php/*.php` 轉到 `/api/*.php` wrapper，再透過 `vercel-php@0.9.0` community runtime 執行 PHP。

Vercel 上必須在專案環境變數設定：

```text
DB_HOST
DB_USER
DB_PASSWORD
DB_NAME
DB_PORT
DB_DRIVER
DB_SSL
DB_SSL_VERIFY
```

Vercel Functions 的檔案系統不適合永久保存上傳圖片或共用登入狀態。本專案的 MySQL 與 PostgreSQL 連線現在都會將商品圖片、頭像保存到資料庫；Vercel 的 MySQL Session 也使用資料庫。本機 MySQL 可設定 `DB_SESSION_STORAGE=database` 使用相同流程。

零預算 TiDB Cloud Starter 的建立、資料搬移、TLS、環境變數與驗證步驟，請參閱根目錄的 [TiDB 遷移說明](../docs/TIDB_STARTER.md)。
