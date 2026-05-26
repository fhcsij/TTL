let allUserProducts = [];
let currentPage = 1;
const itemsPerPage = 6;

function avatarCacheBust(url) {
  return url + (url.includes('?') ? '&t=' : '?t=') + Date.now();
}

function userAvatarSrc(data) {
  return data.avatar_url || ('Image/uploads/' + data.avatar);
}

document.addEventListener('DOMContentLoaded', function () {

  // ✅ 取得使用者資料與頭像
  fetch('php/get_user_info.php')
    .then(res => res.json())
    .then(data => {
      document.getElementById('username').textContent = data.name;
      document.getElementById('avatarImg').src = avatarCacheBust(userAvatarSrc(data));
      document.getElementById('userPoints').textContent = data.points;
    });

  // ✅ 處理頭像上傳
  document.getElementById('avatarUpload').addEventListener('change', function () {
    const formData = new FormData();
    formData.append('avatar', this.files[0]);

    fetch('php/upload_avatar.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('avatarImg').src = avatarCacheBust(data.path);
        } else {
          alert('上傳失敗：' + data.message);
        }
      });
  });

  // 取得分類清單 放入下拉選單
  fetch("php/get_categories.php")
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const select = document.getElementById("categorySelect");
        data.categories.forEach(c => {
          const option = document.createElement("option");
          option.value = c.id;
          option.textContent = c.name;
          select.appendChild(option);
        });
      }
    });

  // 取得分類清單 放入 編輯商品 的下拉選單
  fetch("php/get_categories.php")
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const editSelect = document.getElementById("edit-category");
        data.categories.forEach(c => {
          const option = document.createElement("option");
          option.value = c.id;
          option.textContent = c.name;
          editSelect.appendChild(option);
        });
      }
    });


  // ✅ 顯示使用者上架的商品
  function loadUserProducts() {
    fetch("php/get_user_products.php")
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          allUserProducts = data.products;
          currentPage = 1;
          renderUserProducts();
        } else {
          document.getElementById("productList").innerHTML = "<p class='text-muted'>目前沒有商品。</p>";
        }
      })
      .catch(err => {
        console.error("取得商品失敗", err);
      });
  }

  function renderUserProducts() {
    const productList = document.getElementById("productList");
    const pagination = document.getElementById("userPagination");
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const productsToShow = allUserProducts.slice(start, end);

    productList.innerHTML = "";

    if (productsToShow.length === 0) {
      productList.innerHTML = "<p class='text-muted'>目前沒有商品。</p>";
      return;
    }

    productsToShow.forEach(product => {
      const imageSrc = product.image + (product.image.includes('?') ? '&v=' : '?t=') + Date.now();
      const card = document.createElement("div");
      card.className = "col-md-4 mb-3";
      card.innerHTML = `
        <div class="card product-card" data-id="${product.id}">
          <div class="badge-category">
            ${product.category_name ?? '未分類'}
          </div>
          <img src="${imageSrc}" class="card-img-top" alt="${product.name}">
          <div class="card-body">
            <h5 class="card-title">${product.name}</h5>
            <p class="card-text">價格：$${Math.floor(product.price)}</p>
            <p class="card-text text-muted">${product.description || '&nbsp;'}</p>

            <button class="btn btn-sm delete-btn" style="display:none;">刪除</button>
          </div>
        </div>
      `;
      productList.appendChild(card);

      const cardBody = card.querySelector('.card-body');
      const deleteBtn = cardBody.querySelector('.delete-btn');

      deleteBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const productCard = this.closest('.product-card');
        const productId = productCard.dataset.id;
        if (confirm('確定要刪除此商品？')) {
          fetch('php/delete_product.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + encodeURIComponent(productId)
          })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                alert('刪除成功');
                loadUserProducts();
              } else {
                alert('刪除失敗：' + data.message);
              }
            });
        }
      });

      cardBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-btn')) return;

        document.getElementById('edit-id').value = product.id;
        document.getElementById('edit-name').value = product.name;
        document.getElementById('edit-price').value = product.price;
        document.getElementById('edit-description').value = product.description;
        document.getElementById('edit-preview').src = imageSrc;
        document.getElementById('edit-category').value = product.category_id;

        const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
        modal.show();
      });
    });

    // 分頁按鈕
    pagination.innerHTML = "";
    const totalPages = Math.ceil(allUserProducts.length / itemsPerPage);
    for (let i = 1; i <= totalPages; i++) {
      const li = document.createElement("li");
      li.className = `page-item ${i === currentPage ? "active" : ""}`;
      li.innerHTML = `<button class="page-link">${i}</button>`;
      li.addEventListener("click", () => {
        currentPage = i;
        renderUserProducts();
      });
      pagination.appendChild(li);
    }
  }

  loadUserProducts();

  // ✅ 顯示 / 隱藏刪除按鈕 toggle
  let deleteMode = false;
  document.querySelector(".btn-action:nth-child(2)").addEventListener("click", function () {
    deleteMode = !deleteMode;
    document.querySelectorAll('.delete-btn').forEach(btn => {
      btn.style.display = deleteMode ? 'inline-block' : 'none';
    });
    document.querySelectorAll('.product-card').forEach(card => {
      card.classList.toggle('no-hover', deleteMode);
    });
  });

  // ✅ 新增商品提交
  document.getElementById("addProductForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch("php/add_product.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("商品上架成功！");
          bootstrap.Modal.getInstance(document.getElementById("addProductModal")).hide();
          loadUserProducts();
        } else {
          alert("失敗：" + data.msg);
        }
      })
      .catch(err => {
        console.error("上傳失敗", err);
        alert("伺服器錯誤");
      });
  });

  // ✅ 編輯商品提交
  document.getElementById("editProductForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch("php/edit_product.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("商品更新成功！");
          bootstrap.Modal.getInstance(document.getElementById("editProductModal")).hide();
          loadUserProducts();
        } else {
          alert("更新失敗：" + data.message);
        }
      })
      .catch(err => {
        console.error("更新錯誤", err);
        alert("伺服器錯誤");
      });
  });

  // ✅ 捐贈商品提交
  document.getElementById("donateProductForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch("php/donate_product.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("商品捐贈成功！");
          bootstrap.Modal.getInstance(document.getElementById("donateModal")).hide();
          location.reload(); // ✅ 重新載入頁面（點數也會刷新）
        } else {
          alert("捐贈失敗：" + data.msg);
        }
      })
      .catch(err => {
        console.error("捐贈失敗", err);
        alert("伺服器錯誤");
      });
  });
});
