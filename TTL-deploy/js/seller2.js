function loadDonatedProducts() {
  fetch("php/get_user_donated_products.php")
    .then(res => res.json())
    .then(data => {
      const donatedList = document.getElementById("donatedList");
      donatedList.innerHTML = "";

      if (!data.success || data.products.length === 0) {
        donatedList.innerHTML = "<p class='text-muted'>目前沒有捐贈商品。</p>";
        return;
      }

      data.products.forEach(product => {
        const imageSrc = product.image + (product.image.includes('?') ? '&v=' : '?t=') + Date.now();
        const col = document.createElement("div");
        col.className = "col-md-4 mb-3";
        col.innerHTML = `
          <div class="card product-card">
            <div class="badge-category">
              ${product.category_name ?? '未分類'}
            </div>
            <img src="${imageSrc}" class="card-img-top" alt="${product.name}">
            <div class="card-body">
              <h5 class="card-title">${product.name}</h5>
              <p class="card-text text-muted">${product.description || '&nbsp;'}</p>
            </div>
          </div>
        `;
        donatedList.appendChild(col);
      });
    })
    .catch(err => {
      console.error("載入捐贈商品失敗", err);
    });
}

function avatarCacheBust(url) {
  return url + (url.includes('?') ? '&t=' : '?t=') + Date.now();
}

function userAvatarSrc(data) {
  return data.avatar_url || ('Image/uploads/' + data.avatar);
}


document.addEventListener("DOMContentLoaded", function () {
  // ✅ 載入頭像、名字、點數
  fetch("php/get_user_info.php")
    .then(res => res.json())
    .then(data => {
      document.getElementById("username").textContent = data.name;
      document.getElementById("avatarImg").src = avatarCacheBust(userAvatarSrc(data));
      document.getElementById("userPoints").textContent = data.points;
    });

  // ✅ 綁定頭像上傳功能
  document.getElementById("avatarUpload").addEventListener("change", function () {
    const formData = new FormData();
    formData.append("avatar", this.files[0]);

    fetch("php/upload_avatar.php", {
      method: "POST",
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById("avatarImg").src = avatarCacheBust(data.path);
        } else {
          alert("頭像上傳失敗：" + data.message);
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

document.getElementById("donateProductForm").addEventListener("submit", function (e) {
  e.preventDefault(); // 防止表單預設提交行為

  const form = e.target;
  const formData = new FormData(form);

  fetch("php/donate_product.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("✅ 捐贈成功！");
        form.reset(); // 清空表單
        const modal = bootstrap.Modal.getInstance(document.getElementById("donateModal"));
        modal.hide(); // 關閉 modal
        loadDonatedProducts(); // 重新載入捐贈列表
      } else {
        alert("❌ 捐贈失敗：" + data.message);
      }
    })
    .catch(err => {
      console.error("❌ 捐贈時出錯", err);
      alert("❌ 發生錯誤，請稍後再試");
    });
});


  
  // ✅ 載入捐贈商品列表
  loadDonatedProducts();
});
