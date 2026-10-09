# TTL 改接 TiDB Cloud Starter

## 建立零預算資料庫

1. 在 [TiDB Cloud](https://tidbcloud.com/) 註冊／登入。
2. 選 **Starter**，支出上限保持 **0**，不要切換到 Essential 或付費方案。
3. 建立 instance 後，從 **Connect** 複製實際主機、port 與完整使用者名稱（含 instance prefix）；在 TiDB 後台設定資料庫密碼。
4. 建立 `twotimelight` database，再匯入下述資料表與資料。

官方目前提供每個符合資格的免費 instance 5 GiB row storage、5 GiB columnar storage、每月 5,000 萬 RU；免費額度耗盡會限制新連線。圖片以 base64 存入資料庫，會占用額度，約比原檔增加三分之一容量。

- [方案與額度](https://docs.pingcap.com/tidbcloud/select-cluster-tier/)
- [建立 Starter 與支出上限](https://docs.pingcap.com/tidbcloud/create-tidb-cluster-serverless/?plan=starter)
- [TLS 連線](https://docs.pingcap.com/tidbcloud/secure-connections-to-serverless-clusters/)

## 舊資料的搬移

目前 repo **沒有業務資料表 schema 或 SQL dump**，因此這次修改不會猜測資料表結構，也不會清空 Supabase。

- 先從 Supabase 備份 `public` schema 與所有業務資料；不要只搬 `users` 和 `products`。
- 至少核對 `users`、`categories`、`products`、`cart`、`orders`、`order_items`、`order_complaints`。
- 舊商品與頭像若存在 `product_images`、`user_images`，必須連同 `name`、`mime`、`data` 搬移；檔案名稱要維持一致。
- PostgreSQL dump 不能直接匯入 TiDB。需依實際 schema 轉換型別、identity/sequence、default、索引與外鍵；保留 IDs、關聯、時間、會員點數與密碼雜湊。
- 若使用本機原始 MySQL `twotimelight.sql` 建立 schema，還要比對目前 Supabase 的欄位，並補上部署期間新增的資料。不能用舊 dump 覆蓋新資料。
- `app_sessions` 不搬移：切換後要求使用者重新登入。
- 搬移期間暫停會修改舊庫的操作，完成後比對筆數、關聯、密碼雜湊及圖片內容；保留舊庫備份以供回復。

應用程式會在首次使用時建立三個輔助表：`app_sessions`、`product_images`、`user_images`。資料庫使用者需能建立這些表；業務資料表仍需另外匯入。

## Vercel 設定

參照根目錄 `.env.tidb.example`，在 TTL 專案的 **Production** 環境設定：

| Key | 值 |
| --- | --- |
| `DB_DRIVER` | `mysql`（移除原先的 `pgsql` 值） |
| `DB_HOST` | Connect 顯示的 host，不包含協定與 port |
| `DB_PORT` | Connect 顯示的 port，通常為 `4000` |
| `DB_USER` | 完整名稱，包含 instance prefix |
| `DB_PASSWORD` | TiDB 資料庫密碼 |
| `DB_NAME` | 已建立並匯入資料的 database，例如 `twotimelight` |
| `DB_SSL` | `true` |
| `DB_SSL_VERIFY` | `true` |
| `DB_SSL_CA` | 通常可留空，自動尋找系統 CA bundle；若 runtime 找不到，提供可信 CA bundle 的絕對路徑 |
| `DB_SESSION_STORAGE` | `database` |

`.env.tidb.example` 只是範例；PHP 不會自動讀取它。在本機可用環境變數，或複製 `php/config.local.example.php` 為 `php/config.local.php` 填入上述設定，並設定 `DB_SESSION_STORAGE=database`。

Vercel 會自動使用資料庫保存 MySQL Session；也可在本機用 `DB_SESSION_STORAGE=database` 驗證。商品圖片與頭像對 MySQL 和 PostgreSQL 都會存入資料庫，避免依賴 Function 的本機檔案。repo 中的預設圖片仍可正常使用。

變數變更後需要建立新的部署才會生效：[Vercel 說明](https://vercel.com/docs/environment-variables)。先在隔離的 Preview／測試資料庫驗證，再切換 Production。

## 驗證與切換

1. 用 `php scripts/check_database.php` 驗證連線、driver 與業務資料表是否齊全。此程式只顯示表名，不顯示連線密碼或使用者資料；僅限 CLI。
2. 驗證 `/php/get_categories.php`、`/php/get_all_products.php` 回傳正常 JSON。
3. 使用測試會員登入，再在另一個 Function 請求取得會員資訊，確認 Session 可共用。
4. 上傳測試頭像與商品圖片，再從新的請求讀取圖片；確認重新部署後仍可讀取。
5. 在隔離環境驗證購物車、下單、訂單歷史、捐贈；測試用資料不要寫入真實訂單。
6. 核對資料後切換 Production 設定並重新部署。若有問題，恢復舊庫的環境設定並重新部署；不要刪除舊庫或備份。

本機回歸測試：`php tests/mysql_storage_test.php`。使用 `TEST_DB_HOST`、`TEST_DB_PORT`、`TEST_DB_USER`、`TEST_DB_PASSWORD` 指定測試 MySQL；預設為 `127.0.0.1:33417`。測試會建立隨機命名的 `ttl_storage_test_*` database，最後刪除**該測試 database**，因此測試帳號需具備 CREATE/DROP DATABASE 權限。不要用正式資料庫帳號執行。

這些回歸測試驗證 MySQL 相容的儲存與跨程序 Session；完成 TiDB 建立後仍需驗證實際 TiDB TLS 連線與完整網站流程。
