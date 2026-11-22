window.showAlert=function(o,i,d){const a=document.querySelector(".toast-container")||l(),t="toast-"+Date.now();let s,n;o==="success"?(s="ri-check-line",n="bg-success"):o==="danger"?(s="ri-close-circle-line",n="bg-danger"):(s="ri-error-warning-line",n="bg-danger");const e=d?`
      <div id="${t}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${n} text-white rounded-top-5 border-0">
          <i class="icon-base ${s} me-2"></i>
          <div class="me-auto fw-medium">${i}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body rounded-bottom-5">
          ${d}
        </div>
      </div>
    `:`
      <div id="${t}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${n} text-white rounded-5 border-0">
          <i class="icon-base ${s} me-2"></i>
          <div class="me-auto fw-medium">${i}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;a.insertAdjacentHTML("beforeend",e);const r=document.getElementById(t);new bootstrap.Toast(r,{autohide:!0,delay:3e3}).show(),r.addEventListener("hidden.bs.toast",function(){r.remove()})};function l(){const o=document.createElement("div");return o.className="toast-container position-fixed top-0 end-0 p-3",o.style.zIndex="9999",document.body.appendChild(o),o}$(function(){const o=document.querySelector('meta[name="success-message"]');if(o){const e=o.getAttribute("content");e&&typeof window.showAlert=="function"&&(window.showAlert("success",e),o.remove())}const i=document.querySelector('meta[name="error-message"]');if(i){const e=i.getAttribute("content");e&&typeof window.showAlert=="function"&&(window.showAlert("danger",e),i.remove())}const d=document.querySelectorAll(".delete-policy"),a=new bootstrap.Modal(document.getElementById("deleteModal")),t=document.getElementById("confirmDeleteBtn");let s=null,n=null;d.forEach(e=>{e.addEventListener("click",function(){const r=this.getAttribute("data-id"),c=this.getAttribute("data-name");s=r,n=this.closest("tr"),document.getElementById("deletePolicyName").textContent=c,a.show()})}),t&&t.addEventListener("click",function(){s&&n&&(t.disabled=!0,t.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Deleting...',fetch(baseUrl+`setup-configuration/page-configuration/delete/${s}`,{method:"DELETE",headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content"),"Content-Type":"application/json",Accept:"application/json"}}).then(e=>{if(!e.ok)throw new Error("Network response was not ok");return e.json()}).then(e=>{if(e.success)n.remove(),a.hide(),window.showAlert("success",e.message||"Policy page deleted successfully"),t.disabled=!1,t.innerHTML='<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete',s=null,n=null;else throw new Error(e.message||"Failed to delete policy page")}).catch(e=>{console.error("Error:",e),a.hide(),window.showAlert("danger",e.message||"Failed to delete policy page"),t.disabled=!1,t.innerHTML='<i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete'}))})});
