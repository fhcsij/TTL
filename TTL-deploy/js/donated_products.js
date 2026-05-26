document.addEventListener("DOMContentLoaded", function () {
  fetch("php/get_donated_products.php")
    .then(res => res.json())
    .then(data => {
      if (!data.success || data.products.length === 0) {
        document.getElementById("donatedContainer").innerHTML = "<p class='text-muted'>目前沒有捐贈商品。</p>";
        return;
      }

      const container = document.getElementById("donatedContainer");
      container.innerHTML = "";

      data.products.forEach(product => {
        const col = document.createElement("div");
        col.className = "col-md-3 mb-4";

        col.innerHTML = `
          <div class="card shadow-sm position-relative">
            <img src="${product.image}" class="card-img-top" alt="${product.name}">
            <div class="card-body text-center">
              <h5>${product.name}</h5>
              <p class="text-muted">${product.category_name}</p>
              <p style="font-size: 0.9em; color: #888;">${product.description}</p>
            </div>
            <div class="donor-name">捐贈人：${product.donor_name ?? '匿名'}</div>
          </div>
        `;
        container.appendChild(col);
      });
    });
});
