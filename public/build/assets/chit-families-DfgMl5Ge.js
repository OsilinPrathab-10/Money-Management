document.addEventListener("DOMContentLoaded",function(){var F,C,M;typeof $<"u"&&$.fn.select2&&($(".select2").select2({dropdownParent:$(".modal.show").length?$(".modal.show"):$(document.body),width:"100%"}),$(".modal").on("shown.bs.modal",function(){$(this).find(".select2").select2({dropdownParent:$(this),width:"100%"})}));const P=window.chitFamilyConfig||{},T=(F=document.querySelector('meta[name="csrf-token"]'))==null?void 0:F.getAttribute("content"),N=["Husband","Father","Mother","Son","Daughter","Spouse","Brother","Sister","Other"];function S(){const e=document.getElementById("familyMembersSelect"),l=document.getElementById("memberRelationshipsWrap"),o=document.getElementById("memberRelationships");if(!e||!l||!o)return;let s={};try{s=JSON.parse(e.dataset.clientLabels||"{}")}catch{s={}}const u=Array.from(e.selectedOptions).map(t=>t.value);if(o.innerHTML="",!u.length){l.style.display="none";return}l.style.display="",u.forEach(t=>{const i=s[t]||`Client #${t}`,d=document.createElement("div");d.className="col-md-6",d.innerHTML=`
        <label class="form-label small mb-1">${i}</label>
        <select name="relationships[${t}]" class="form-select form-select-sm">
          <option value="">— None —</option>
          ${N.map(m=>`<option value="${m}">${m}</option>`).join("")}
        </select>
      `,o.appendChild(d)})}const B=document.getElementById("familyMembersSelect");B&&(B.addEventListener("change",S),typeof $<"u"&&$(B).on("select2:select select2:unselect",S),S());function I(e){return"₹"+Number(e||0).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2})}function h(){const e=document.querySelectorAll(".due-checkbox:checked");let l=0;const o=[];return e.forEach(s=>{o.push(s.value),l+=parseFloat(s.dataset.balance||0)}),{ids:o,total:l,count:o.length}}function E(){const{total:e,count:l}=h(),o=document.querySelectorAll("#selectedCount"),s=document.getElementById("selectedTotal"),u=document.getElementById("barTotalAmount"),t=document.getElementById("bulkPayBar"),i=document.getElementById("btnBulkPayAll");o.forEach(d=>{d.textContent=l}),s&&(s.textContent=I(e)),u&&(u.textContent=I(e)),i&&(i.disabled=l===0),t&&t.classList.toggle("d-none",l===0)}const _=document.getElementById("selectAllDues");_&&_.addEventListener("change",function(){document.querySelectorAll(".due-checkbox").forEach(e=>{e.checked=_.checked}),E()}),document.querySelectorAll(".due-checkbox").forEach(e=>{e.addEventListener("change",E)}),document.querySelectorAll(".due-checkbox").length&&E();const y=document.getElementById("payTypeFull"),f=document.getElementById("payTypePartial"),g=document.getElementById("totalAmountPaidWrapper"),r=document.getElementById("totalAmountPaidInput"),k=document.getElementById("totalAmountLabel");function v(){if(f!=null&&f.checked){if(k&&(k.textContent="Selected Total"),g&&(g.style.display="block"),r){r.required=!0;const{total:e}=h();r.value||(r.value=e.toFixed(2))}}else k&&(k.textContent="Total Amount"),g&&(g.style.display="none"),r&&(r.required=!1)}y==null||y.addEventListener("change",v),f==null||f.addEventListener("change",v);function A(){const{total:e,count:l}=h();if(!l){Swal.fire({icon:"warning",title:"No Selection",text:"Please select at least one installment to pay."});return}document.getElementById("bulkPayCount").textContent=l,document.getElementById("bulkPayAmount").value=e.toFixed(2),y&&(y.checked=!0),r&&(r.value=e.toFixed(2)),v(),window.BankPaymentFields&&window.BankPaymentFields.resetToInHand("bulkPayMethod"),new bootstrap.Modal(document.getElementById("bulkPayModal")).show()}(C=document.getElementById("btnBulkPayAll"))==null||C.addEventListener("click",A),(M=document.getElementById("btnOpenBulkPayModal"))==null||M.addEventListener("click",A);const x=document.getElementById("bulkPayForm");x&&P.bulkCollectUrl&&x.addEventListener("submit",function(e){e.preventDefault();const{ids:l,count:o}=h();if(!o)return;const s=document.getElementById("bulkPayMethod"),u=document.getElementById("familyBulkBankAccount");if(window.BankPaymentFields){const b=window.BankPaymentFields.validateBankPayment(s,u,x);if(b){Swal.fire({icon:"warning",title:"Action Required",text:b});return}}const t=new FormData(x),i=t.get("payment_mode"),d=t.get("internal_bank_account_id");if((i==="upi"||i==="bank_transfer")&&!d){Swal.fire({icon:"warning",title:"Bank Account Required",text:"Collection Bank Account is mandatory when paying via UPI / GPay / QR or Bank Transfer."});return}const m=document.getElementById("bulkPaySubmitBtn"),w=m.querySelector(".spinner-border");m.disabled=!0,w&&w.classList.remove("d-none");const D={installment_ids:l,payment_mode:i,paid_date:t.get("paid_date"),reference_no:t.get("reference_no")||null,remarks:t.get("remarks")||null,collection_type:t.get("collection_type")||"full",total_amount_paid:t.get("collection_type")==="partial"?parseFloat(t.get("total_amount_paid")):null,internal_bank_account_id:i==="upi"||i==="bank_transfer"?d:null};fetch(P.bulkCollectUrl,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":T,Accept:"application/json"},body:JSON.stringify(D)}).then(b=>b.json().then(n=>({ok:b.ok,data:n}))).then(({ok:b,data:n})=>{var L;if(m.disabled=!1,w&&w.classList.add("d-none"),b&&n.success){(L=bootstrap.Modal.getInstance(document.getElementById("bulkPayModal")))==null||L.hide();let c=`<div class="alert alert-success py-2 px-3 mb-3 text-start small">${n.message}</div>`;n.collected_members&&n.collected_members.length>0&&(c+=`
                <div class="text-start mb-2">
                  <span class="fw-semibold text-heading small">Send Separate Notifications to Paid Members:</span>
                </div>
                <div class="table-responsive text-start border rounded mb-3" style="max-height: 220px;">
                  <table class="table table-sm text-nowrap mb-0 align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Member</th>
                        <th>Amount Paid</th>
                        <th class="text-center">Send Separate</th>
                      </tr>
                    </thead>
                    <tbody>
              `,n.collected_members.forEach(a=>{const q="₹"+Number(a.amount_paid||0).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});let p="";a.whatsapp_url&&(p+=`<a href="${a.whatsapp_url}" target="_blank" class="btn btn-xs btn-icon btn-text-secondary rounded-pill text-success me-1" title="Send WhatsApp to ${a.client_name}">
                    <i class="icon-base ri ri-whatsapp-line icon-18px"></i>
                  </a>`),a.sms_url&&(p+=`<a href="${a.sms_url}" class="btn btn-xs btn-icon btn-text-secondary rounded-pill text-info me-1" title="Send SMS to ${a.client_name}">
                    <i class="icon-base ri ri-message-3-line icon-18px"></i>
                  </a>`),a.client_phone&&(p+=`<a href="tel:${a.client_phone.replace(/\s+/g,"")}" class="btn btn-xs btn-icon btn-text-secondary rounded-pill" title="Call ${a.client_name}">
                    <i class="icon-base ri ri-phone-line icon-18px"></i>
                  </a>`),p||(p='<span class="text-muted small">No Phone</span>'),c+=`
                  <tr>
                    <td>
                      <div class="fw-semibold small">${a.client_name}</div>
                      <small class="text-muted">${a.client_phone||""}</small>
                    </td>
                    <td class="fw-semibold text-success small">${q}</td>
                    <td class="text-center">${p}</td>
                  </tr>
                `}),c+=`
                    </tbody>
                  </table>
                </div>
              `),(n.whatsapp_url||n.sms_url)&&(c+=`
                <div class="text-start mb-2">
                  <span class="fw-semibold text-heading small">Send Primary / Family Summary Confirmation:</span>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
              `,n.whatsapp_url&&(c+=`<a href="${n.whatsapp_url}" target="_blank" class="btn btn-sm btn-success"><i class="icon-base ri ri-whatsapp-line me-1"></i> WhatsApp Summary</a>`),n.sms_url&&(c+=`<a href="${n.sms_url}" class="btn btn-sm btn-info"><i class="icon-base ri ri-message-3-line me-1"></i> SMS Summary</a>`),c+="</div>"),Swal.fire({icon:"success",title:"Family Payment Completed!",html:c,confirmButtonText:"Done / Close",customClass:{confirmButton:"btn btn-secondary mt-3"},buttonsStyling:!1}).then(()=>window.location.reload())}else{const c=n.message||(n.errors?Object.values(n.errors).flat().join(" "):"Payment failed.");Swal.fire({icon:"error",title:"Error",text:c})}}).catch(()=>{m.disabled=!1,m.innerHTML=originalText,Swal.fire({icon:"error",title:"Error",text:"Network error. Please try again."})})})});
