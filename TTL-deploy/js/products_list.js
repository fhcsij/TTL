let allProducts = []; // 儲存所有商品資料
let currentPage = 1;
const itemsPerPage = 12;
let cartProductIds = []; // 購物車中的商品 ID

document.addEventListener('DOMContentLoaded', function () {
  // 先取得購物車內容，再載入商品
  fetch("php/get_cart_items.php")
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        cartProductIds = data.items.map(item => item.product_id);
      }
    })
    .finally(() => {
      fetch("php/get_all_products.php")
        .then(res => res.json())
        .then(data => {
          if (data.success && data.products.length > 0) {
            allProducts = data.products;
            renderProducts(allProducts);
          } else {
            document.getElementById("productList").innerHTML = "<p class='text-muted'>目前沒有商品。</p>";
          }
        })
        .catch(err => {
          console.error("商品讀取失敗：", err);
        });
    });

  // 搜尋商品名稱
  document.querySelector(".search-bar input").addEventListener("input", function () {
    const keyword = this.value.toLowerCase();
    document.querySelectorAll("#productList .card").forEach(card => {
      const title = card.querySelector("h5").textContent.toLowerCase();
      card.parentElement.style.display = title.includes(keyword) ? "" : "none";
    });
  });

  // 套用分類篩選
  document.getElementById("applyFilterBtn").addEventListener("click", function () {
    const checkedCategories = Array.from(document.querySelectorAll(".category-filter:checked")).map(cb => cb.value);

    const minPrice = parseFloat(document.getElementById("minPrice").value) || 0;
    const maxPrice = parseFloat(document.getElementById("maxPrice").value) || Infinity;

    const filtered = allProducts.filter(p => {
      const price = Number(p.price);
      const inCategory = checkedCategories.length === 0 || checkedCategories.includes(p.category_name);
      const inPriceRange = price >= minPrice && price <= maxPrice;
      return inCategory && inPriceRange;
    });

    currentPage = 1;
    renderProducts(filtered);
  });
  // 清除篩選
  document.getElementById("clearFilterBtn").addEventListener("click", function () {
    document.getElementById("minPrice").value = "";
    document.getElementById("maxPrice").value = "";
    document.querySelectorAll(".category-filter").forEach(cb => cb.checked = false);

    currentPage = 1;
    renderProducts(allProducts); // 顯示全部商品
  });
});

// 渲染商品卡片 + 分頁
function renderProducts(products) {
  const productList = document.getElementById("productList");
  const pagination = document.getElementById("pagination");

  const totalPages = Math.ceil(products.length / itemsPerPage);
  const start = (currentPage - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  const productsToShow = products.slice(start, end);

  productList.innerHTML = "";

  productsToShow.forEach(product => {
    const productId = Number(product.id);
    const col = document.createElement("div");
    col.className = "col-md-3 mb-4";

    const addToCartButton = cartProductIds.includes(productId)
      ? '<button class="btn btn-secondary btn-sm" disabled>已在購物車</button>'
      : `<button class="btn btn-outline-secondary btn-sm add-to-cart-btn" data-id="${productId}">＋</button>`;

    col.innerHTML = `
      <div class="card shadow-sm position-relative">
        <div class="badge-category">
          ${product.category_name ?? '未分類'}
        </div>
        <img src="${product.image}" class="card-img-top" alt="${product.name}">
        <div class="card-body d-flex flex-column justify-content-between text-start position-relative">
          <div>
            <h2>${product.name}</h2>
            <p style="font-size: 0.9em; color: #888; min-height: 2.5em;">
              商品描述：${product.description || '&nbsp;'}
            </p>
            <br>
            <p style="font-size: 20px; color: #568589; font-weight: bold;">$${Math.floor(product.price)}</p>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <span style="font-size: 0.85em; color: #888;">賣家：${product.seller_name ?? '未知'}</span>
            ${addToCartButton}
          </div>
        </div>

        </div>
    `;
    productList.appendChild(col);
  });

  // 加入購物車按鈕事件
  setTimeout(() => {
    document.querySelectorAll(".add-to-cart-btn").forEach(btn => {
      btn.addEventListener("click", function () {
        const productId = this.dataset.id;
        addToCart(productId);
      });
    });
  }, 0);

  // 分頁按鈕
  pagination.innerHTML = "";
  for (let i = 1; i <= totalPages; i++) {
    const li = document.createElement("li");
    li.className = `page-item ${i === currentPage ? "active" : ""}`;
    li.innerHTML = `<button class="page-link">${i}</button>`;
    li.addEventListener("click", () => {
      currentPage = i;
      renderProducts(products);
    });
    pagination.appendChild(li);
  }
}


// 加入購物車函式
function addToCart(productId) {
  fetch("php/add_to_cart.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: `product_id=${encodeURIComponent(productId)}`
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert(" 已加入購物車！");
        cartProductIds.push(Number(productId));
        renderProducts(allProducts); // 重新渲染按鈕狀態
      } else {
        alert(" 加入失敗：" + data.message);
      }
    })
    .catch(err => {
      console.error("加入購物車錯誤：", err);
      alert(" 發生錯誤");
    });
}
