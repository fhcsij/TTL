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

根目錄的 `vercel.json` 會把網站公開路徑導到 `TTL-deploy`，並把 `/php/*.php` 轉到 `/api/*.php` wrapper，再透過 `vercel-php` community runtime 執行 PHP。

Vercel 上必須在專案環境變數設定：

```text
DB_HOST
DB_USER
DB_PASSWORD
DB_NAME
DB_PORT
```

另外，Vercel Functions 的檔案系統不適合永久保存使用者上傳圖片；公開 repo 也不提交 `Image/uploads` 中的使用者上傳資料，只保留預設頭像。若要讓頭像與商品圖片上傳在正式站穩定運作，需要改接外部儲存服務，例如 Supabase Storage 或 Vercel Blob。
