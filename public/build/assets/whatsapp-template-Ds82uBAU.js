(function(){document.querySelectorAll(".status-toggle").forEach(t=>{t.addEventListener("change",function(){const o=this.getAttribute("data-id"),n=this.checked,a=document.querySelector(`.status-badge-${o}`);fetch(`${window.baseUrl}templates/whatsapp/${o}/toggle-status`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")},body:JSON.stringify({is_active:n})}).then(e=>e.json()).then(e=>{e.success?(a&&(a.textContent=e.is_active?"Active":"Inactive",a.className=`badge rounded-pill bg-label-${e.is_active?"success":"secondary"} status-badge-${o}`),l("success",e.message)):(this.checked=!n,l("danger",e.message||"Failed to update template status"))}).catch(e=>{console.error("Error:",e),this.checked=!n,l("danger","An error occurred while updating the template status")})})});const T=document.querySelectorAll(".delete-template"),m=document.getElementById("deleteModal"),b=document.getElementById("deleteTemplateName"),p=document.getElementById("confirmDeleteBtn");let u=null;T.forEach(t=>{t.addEventListener("click",function(){u=this.getAttribute("data-id");const o=this.getAttribute("data-name");b&&(b.textContent=o),new bootstrap.Modal(m).show()})}),p&&p.addEventListener("click",function(){u&&fetch(`${window.baseUrl}templates/whatsapp/${u}/delete`,{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")}}).then(t=>t.json()).then(t=>{bootstrap.Modal.getInstance(m).hide(),t.success?(l("success",t.message),setTimeout(()=>{window.location.reload()},1e3)):l("danger",t.message||"Failed to delete template")}).catch(t=>{console.error("Error:",t),bootstrap.Modal.getInstance(m).hide(),l("danger","An error occurred while deleting the template")})});const v=document.getElementById("variablesContainer"),h=document.getElementById("addVariable"),f=document.getElementById("templateForm"),y=document.getElementById("variablesJson");if(v&&h&&f){let t=function(){const a=Array.from(document.querySelectorAll(".variable-position")).map(e=>parseInt(e.value)||0);return a.length>0?Math.max(...a)+1:1},o=function(a){a.addEventListener("click",function(){this.closest(".variable-row").remove(),n()})},n=function(){document.querySelectorAll(".variable-row").forEach((e,r)=>{const s=e.querySelector(".variable-position");s.value=r+1})};var L=t,S=o,B=n;h.addEventListener("click",function(){const a=t(),e=document.createElement("div");e.className="variable-row mb-3",e.innerHTML=`
        <div class="row g-2 align-items-center">
          <div class="col-md-1">
            <input type="text" class="form-control variable-position" 
              placeholder="Position" value="${a}" readonly>
          </div>
          <div class="col-md-10">
            <input type="text" class="form-control variable-name" 
              placeholder="Variable name (e.g., customer_name)">
          </div>
          <div class="col-md-1 text-center">
            <a href="javascript:void(0);" class="remove-variable" title="Remove">
              <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
            </a>
          </div>
        </div>
      `,v.appendChild(e),o(e.querySelector(".remove-variable"))}),document.querySelectorAll(".remove-variable").forEach(a=>{o(a)}),f.addEventListener("submit",function(a){const e={};document.querySelectorAll(".variable-row").forEach(s=>{const c=s.querySelector(".variable-position").value,i=s.querySelector(".variable-name").value.trim();c&&i&&(e[c]=i)}),Object.keys(e).length>0?y.value=JSON.stringify(e):y.value=""})}function l(t,o,n){const a=document.querySelector(".toast-container")||x(),e="toast-"+Date.now();let r,s;t==="success"?(r="ri-check-line",s="bg-success"):t==="danger"?(r="ri-close-circle-line",s="bg-danger"):t==="warning"?(r="ri-error-warning-line",s="bg-warning"):(r="ri-information-line",s="bg-info");const c=n?`
          <div id="${e}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
            <div class="toast-header ${s} text-white rounded-top-5 border-0">
              <i class="icon-base ${r} me-2"></i>
              <div class="me-auto fw-medium">${o}</div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body rounded-bottom-3">
              ${n}
            </div>
          </div>
        `:`
          <div id="${e}" class="bs-toast toast fade show rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
            <div class="toast-header ${s} text-white rounded-3 border-0">
              <i class="icon-base ${r} me-2"></i>
              <div class="me-auto fw-medium">${o}</div>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
          </div>
        `;a.insertAdjacentHTML("beforeend",c);const i=document.getElementById(e);new bootstrap.Toast(i,{autohide:!0,delay:3e3}).show(),i.addEventListener("hidden.bs.toast",function(){i.remove()})}function x(){const t=document.createElement("div");return t.className="toast-container position-fixed top-0 end-0 p-3",t.style.zIndex="9999",document.body.appendChild(t),t}const d=document.querySelector(".alert-container");if(d){const t=d.getAttribute("data-success"),o=d.getAttribute("data-error"),n=d.getAttribute("data-validation");t&&l("success","Success",t),o&&l("danger","Error",o),n&&l("danger","Validation Error",n)}const E=document.querySelector('meta[name="success-message"]'),w=document.querySelector('meta[name="error-message"]');E&&l("success","Success",E.getAttribute("content")),w&&l("danger","Error",w.getAttribute("content"));const I=document.getElementById("fetchGallaboxBtn"),g=document.getElementById("gallaboxTemplatesModal");I&&g&&I.addEventListener("click",function(){new bootstrap.Modal(g).show(),document.getElementById("gallaboxTemplatesLoading").classList.remove("d-none"),document.getElementById("gallaboxTemplatesError").classList.add("d-none"),document.getElementById("gallaboxTemplatesList").classList.add("d-none"),fetch(`${window.baseUrl}templates/whatsapp/fetch-gallabox`,{method:"GET",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")}}).then(o=>o.json()).then(o=>{if(document.getElementById("gallaboxTemplatesLoading").classList.add("d-none"),o.success&&o.templates&&o.templates.length>0){document.getElementById("gallaboxTemplatesList").classList.remove("d-none");const n=document.getElementById("gallaboxTemplatesBody");n.innerHTML="",o.templates.forEach(a=>{const e=document.createElement("tr");e.innerHTML=`
                                <td><strong>${a.name||"N/A"}</strong></td>
                                <td>${a.language||"en"}</td>
                                <td><span class="badge bg-label-success">${a.status||"APPROVED"}</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary select-template" 
                                        data-name="${a.name||""}"
                                        data-language="${a.language||"en"}">
                                        <i class="ri-add-line me-1"></i> Select
                                    </button>
                                </td>
                            `,n.appendChild(e)}),document.querySelectorAll(".select-template").forEach(a=>{a.addEventListener("click",function(){const e=this.getAttribute("data-name"),r=this.getAttribute("data-language");bootstrap.Modal.getInstance(g).hide();const c=new URL("/templates/whatsapp/create",window.location.origin);c.searchParams.set("template_name",e),c.searchParams.set("language",r),window.location.href=c.toString()})})}else{const n=document.getElementById("gallaboxTemplatesError");n.textContent=o.message||"No approved templates found in Gallabox.",n.classList.remove("d-none")}}).catch(o=>{console.error("Error:",o),document.getElementById("gallaboxTemplatesLoading").classList.add("d-none");const n=document.getElementById("gallaboxTemplatesError");n.textContent="Failed to fetch templates from Gallabox. Please check your API configuration.",n.classList.remove("d-none")})})})();
