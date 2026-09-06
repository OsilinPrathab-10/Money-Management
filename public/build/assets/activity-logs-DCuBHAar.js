document.addEventListener("DOMContentLoaded",function(){let s=null,l=null;function d(t,o){const n=document.querySelector(".toast-container")||u(),e="toast-"+Date.now();let a,i;t==="success"?(a="ri-check-line",i="bg-success"):t==="danger"?(a="ri-close-circle-line",i="bg-danger"):t==="warning"?(a="ri-alert-line",i="bg-warning"):t==="info"?(a="ri-information-line",i="bg-info"):(a="ri-error-warning-line",i="bg-danger");const f=`
      <div id="${e}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${i} text-white rounded-5 border-0">
          <i class="icon-base ${a} me-2"></i>
          <div class="me-auto fw-medium">${o}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;n.insertAdjacentHTML("beforeend",f);const m=document.getElementById(e);new bootstrap.Toast(m,{autohide:!0,delay:3e3}).show(),m.addEventListener("hidden.bs.toast",function(){m.remove()})}function u(){const t=document.createElement("div");return t.className="toast-container position-fixed top-0 end-0 p-3",t.style.zIndex="9999",document.body.appendChild(t),t}const c=document.querySelector(".alert-container");if(c){const t=c.getAttribute("data-success"),o=c.getAttribute("data-error"),n=c.getAttribute("data-warning"),e=c.getAttribute("data-info");t&&d("success",t),o&&d("danger",o),n&&d("warning",n),e&&d("info",e)}const r=new bootstrap.Modal(document.getElementById("locationMapModal"));document.querySelectorAll(".view-location-btn").forEach(t=>{t.addEventListener("click",function(){const o=this.getAttribute("data-activity-id");g(o)})});function g(t){r.show();const o=document.getElementById("locationMap");o.innerHTML=`
      <div class="d-flex align-items-center justify-content-center h-100">
        <div class="text-center">
          <div class="spinner-border text-primary mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="text-muted">Loading location data...</p>
        </div>
      </div>
    `,fetch(baseUrl+"audit-logs/activity-logs/location/"+t,{method:"GET",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}}).then(n=>n.json()).then(n=>{if(n.success){const e=n.data;if(document.getElementById("modalClientName").textContent=e.client_name,document.getElementById("modalPhone").textContent=e.phone,document.getElementById("modalDevice").textContent=e.device_name||e.device_model||"Unknown",document.getElementById("modalIpAddress").textContent=e.ip_address||"N/A",document.getElementById("modalLoginAt").textContent=e.login_at,document.getElementById("modalLogoutAt").textContent=e.logout_at,e.latitude&&e.longitude){const a=parseFloat(e.latitude),i=parseFloat(e.longitude);document.getElementById("modalCoordinates").textContent=`Latitude: ${a}, Longitude: ${i}`,p(a,i)}else o.innerHTML=`
            <div class="d-flex align-items-center justify-content-center h-100">
              <div class="text-center">
                <i class="icon-base ri ri-map-pin-off-line text-muted" style="font-size: 48px;"></i>
                <p class="text-muted mt-3 mb-0">Location data not available</p>
              </div>
            </div>
          `,document.getElementById("modalCoordinates").textContent="Location not recorded"}else d("danger",n.message||"Failed to load location details"),r.hide()}).catch(n=>{console.error("Error:",n),d("danger","Failed to load location details. Please try again."),r.hide()})}function p(t,o){const n=document.getElementById("locationMap");n.innerHTML="",typeof L<"u"?(s=L.map("locationMap").setView([t,o],15),L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"© OpenStreetMap contributors",maxZoom:19}).addTo(s),l=L.marker([t,o]).addTo(s),l.bindPopup("<b>Last Login Location</b>").openPopup()):n.innerHTML=`
        <img src="https://maps.googleapis.com/maps/api/staticmap?center=${t},${o}&zoom=15&size=600x400&markers=color:red%7C${t},${o}&key=YOUR_GOOGLE_MAPS_API_KEY" 
          alt="Location Map" 
          class="w-100 h-100" 
          style="object-fit: cover;">
      `}document.getElementById("locationMapModal").addEventListener("hidden.bs.modal",function(){s&&(s.remove(),s=null,l=null)})});
