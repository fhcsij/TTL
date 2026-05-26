# 個人作品集網站

這是一個可直接部署的靜態作品集網站。主要內容在 `index.html`，作品資料在 `script.js`，視覺樣式在 `styles.css`。

## 修改內容

1. 在 `index.html` 替換姓名、簡介、email、GitHub、LinkedIn。
2. 在 `script.js` 的 `projects` 陣列替換作品名稱、描述、分類與技術標籤。
3. 用你的照片或代表圖覆蓋 `assets/portfolio-visual.png`。
4. 若要改色彩，調整 `styles.css` 最上方的 `:root` 變數。

## 本機預覽

直接用瀏覽器開啟 `index.html` 即可預覽。

如果想用本機伺服器預覽，也可以在此資料夾執行：

```powershell
python -m http.server 5500
```

接著開啟 `http://localhost:5500`。

## 部署到 GitHub Pages

1. 建立一個 GitHub repository。
2. 將這個資料夾推上 GitHub。
3. 到 repository 的 `Settings` → `Pages`。
4. `Build and deployment` 選擇 `GitHub Actions`。
5. 推送到 `main` 後，`.github/workflows/pages.yml` 會自動發布。
6. 等 Actions 完成後，頁面會出現在 `https://你的帳號.github.io/你的-repo/`。

## 部署到 Netlify

1. 登入 Netlify，選擇 `Add new site`。
2. 選擇從 GitHub 匯入 repository，或直接拖曳整個資料夾。
3. Build command 留空。
4. Publish directory 填 `.`。
5. 部署完成後即可取得公開網址。

## 部署到 Vercel

1. 登入 Vercel，選擇 `Add New Project`。
2. 匯入 GitHub repository。
3. Framework Preset 選擇 `Other`。
4. Build command 留空。
5. Output directory 填 `.`。
6. 部署完成後即可取得公開網址。
