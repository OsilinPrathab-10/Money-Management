(function(){let p=null;document.addEventListener("DOMContentLoaded",function(){const e=document.getElementById("prepaymentBtn"),t=new bootstrap.Modal(document.getElementById("prepaymentModal")),n=document.getElementById("prepaymentAmount"),r=document.getElementById("confirmPrepaymentCheck"),s=document.getElementById("confirmPrepaymentBtn");e&&(e.addEventListener("click",function(){p=this.dataset.accountId,this.dataset.accountNumber,I(),x(p),t.show()}),n&&n.addEventListener("input",_(function(){const c=parseFloat(this.value);c&&c>0?E(p,c):g()},500)),r&&r.addEventListener("change",function(){s.disabled=!this.checked}),s&&s.addEventListener("click",function(){$()}))});function x(e){fetch(`/loan-accounts/${e}/prepayment-info`).then(t=>t.json()).then(t=>{v(t),w(t)}).catch(t=>{console.error("Error fetching prepayment info:",t),m("prepaymentEligibilityAlert","danger","Failed to load prepayment information")})}function E(e,t){fetch(`/loan-accounts/${e}/prepayment-info?amount=${t}`).then(n=>n.json()).then(n=>{n.success===!1?(m("prepaymentEligibilityAlert","danger",n.message),g()):B(n)}).catch(n=>{console.error("Error fetching prepayment breakdown:",n)})}function v(e){document.getElementById("prepaymentEligibilityAlert"),e.is_eligible?m("prepaymentEligibilityAlert","success",`✓ Eligible for prepayment (${e.paid_emis_count}/${e.eligibility_months} EMIs completed)`):(m("prepaymentEligibilityAlert","warning",`Not eligible for prepayment yet. Complete ${e.eligibility_months-e.paid_emis_count} more EMI(s)`),document.getElementById("prepaymentAmount").disabled=!0)}function w(e){document.getElementById("minPrepaymentAmount").textContent=`₹${o(e.min_amount)}`,document.getElementById("maxPrepaymentAmount").textContent=`₹${o(e.max_amount)}`}function B(e){document.getElementById("prepaymentOutstanding").textContent=`₹${o(e.outstanding_amount)}`,document.getElementById("prepaymentAmountDisplay").textContent=`₹${o(e.amount)}`,document.getElementById("prepaymentInterest").textContent=`₹${o(e.interest_portion)}`,document.getElementById("prepaymentChargesPercent").textContent=o(e.prepayment_charge_percentage),document.getElementById("prepaymentCharges").textContent=`₹${o(e.prepayment_charge_amount)}`,document.getElementById("prepaymentTotalPayable").textContent=`₹${o(e.total_payable_amount)}`,document.getElementById("prepaymentNewOutstanding").textContent=`₹${o(e.revised_principal)}`}function g(){document.getElementById("prepaymentOutstanding").textContent="₹0.00",document.getElementById("prepaymentAmountDisplay").textContent="₹0.00",document.getElementById("prepaymentInterest").textContent="₹0.00",document.getElementById("prepaymentCharges").textContent="₹0.00",document.getElementById("prepaymentTotalPayable").textContent="₹0.00",document.getElementById("prepaymentNewOutstanding").textContent="₹0.00"}function I(){document.getElementById("prepaymentAmount").value="",document.getElementById("prepaymentAmount").disabled=!1,document.getElementById("prepaymentPaymentMethod").value="cash",document.getElementById("prepaymentReference").value="",document.getElementById("prepaymentRemarks").value="",document.getElementById("confirmPrepaymentCheck").checked=!1,document.getElementById("confirmPrepaymentBtn").disabled=!0,g()}function $(){const e=parseFloat(document.getElementById("prepaymentAmount").value),t=document.getElementById("prepaymentPaymentMethod").value,n=document.getElementById("prepaymentReference").value,r=document.getElementById("prepaymentRemarks").value;if(!e||e<=0){m("prepaymentEligibilityAlert","danger","Please enter a valid prepayment amount");return}const s=document.getElementById("confirmPrepaymentBtn");s.disabled=!0,s.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Processing...';const c={amount:e,payment_method:t,payment_reference:n,remarks:r,payment_date:new Date().toISOString().split("T")[0]};fetch(`/loan-accounts/${p}/prepayment`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').getAttribute("content")},body:JSON.stringify(c)}).then(a=>a.json()).then(a=>{if(a.success){const u=a.data.new_tenure-a.data.old_tenure,P=u<0,f=Math.abs(u),C=P?`<span class="text-success fw-bold"><i class="ri-arrow-down-line"></i> ${f} Months</span>`:u>0?`<span class="text-warning fw-bold"><i class="ri-arrow-up-line"></i> ${f} Months</span>`:'<span class="text-muted">No Change</span>';let b="";if(a.sms_data){const i=a.sms_data,k=i.client_name||"Client";let l=(i.mobile_no||"").replace(/\D/g,"");l.length===10&&(l="91"+l);const M=i.account_no||"",A=parseFloat(i.amount_paid||0).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2}),S=parseFloat(i.remaining_balance||0).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2});let y=i.sms_message||"",d=i.whatsapp_message||"";if(!y||!d){const h=`Dear ${k},
Your Prepayment (Principal payment) of ₹${A} towards Shanmuga Finance Loan Account ${M} has been received successfully.
Outstanding Principal Balance: ₹${S}.
Thank you!`;if(y||(y=h),!d&&(d=h,i.application_number)){const N=btoa(i.application_number),L=`${window.location.origin}/view-schedule/${N}`;d+=`

Please check your EMI Schedule here: ${L}`}}const T=/iPad|iPhone|iPod/.test(navigator.userAgent)||navigator.platform==="MacIntel"&&navigator.maxTouchPoints>1?"&":"?",D=`https://wa.me/${l}?text=${encodeURIComponent(d)}`,F=`sms:+${l}${T}body=${encodeURIComponent(y)}`;b=`
                        <div class="mt-4 pt-2 border-top">
                            <p class="text-muted small mb-2 text-center">Send prepayment confirmation receipt to client number: <strong>+${l}</strong></p>
                            <div class="d-grid gap-2">
                              <a href="${D}" target="_blank" class="btn btn-success d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #25D366; border-color: #25D366; color: white; font-weight: 500;">
                                <i class="ri-whatsapp-line fs-5"></i> Send WhatsApp Confirmation
                              </a>
                              
                              <a href="${F}" class="btn btn-info d-flex align-items-center justify-content-center gap-2 py-2" style="background-color: #0088cc; border-color: #0088cc; color: white; font-weight: 500;">
                                <i class="ri-message-3-line fs-5"></i> Send Native SMS
                              </a>
                            </div>
                        </div>
                      `}Swal.fire({title:"",html:`
                            <div class="text-start">
                                <div class="text-center mb-4">
                                    <div class="avatar avatar-xl bg-label-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                                        <i class="ri-checkbox-circle-line fs-1 text-success"></i>
                                    </div>
                                    <h4 class="mb-1">Prepayment Successful!</h4>
                                    <p class="text-muted">The transaction has been processed successfully.</p>
                                </div>

                                <!-- Payment Summary Box -->
                                <div class="bg-label-secondary p-3 rounded mb-4">
                                    <h6 class="fw-bold mb-3 text-uppercase small text-muted">Transaction Summary</h6>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-body">Prepayment Amount</span>
                                        <span class="fw-bold">₹${o(a.data.prepayment_amount)}</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-body">Charges</span>
                                        <span>₹${o(a.data.prepayment_charge)}</span>
                                    </div>
                                    <hr class="my-3 border-secondary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-heading">Total Paid</span>
                                        <span class="h4 mb-0 text-primary fw-bold">₹${o(a.data.total_payable)}</span>
                                    </div>
                                </div>

                                <!-- Key Metrics Grid -->
                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="p-3 border rounded text-center h-100">
                                            <small class="text-muted d-block mb-1 text-uppercase">New Outstanding</small>
                                            <h5 class="mb-0 text-success fw-bold">₹${o(a.data.new_principal)}</h5>
                                            <small class="text-muted" style="font-size: 0.75rem">Was: ₹${o(a.data.old_principal)}</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded text-center h-100">
                                            <small class="text-muted d-block mb-1 text-uppercase">Tenure Adjustment</small>
                                            <h5 class="mb-0">${C}</h5>
                                            <small class="text-muted" style="font-size: 0.75rem">${a.data.old_tenure} ➔ ${a.data.new_tenure} Months</small>
                                        </div>
                                    </div>
                                </div>

                                ${u>0?`
                                <div class="alert alert-warning py-2 small mb-0 text-start">
                                    <i class="ri-information-line me-1"></i> Tenure adjusted to reflect current outstanding.
                                </div>
                                `:""}

                                ${b}
                            </div>
                        `,showConfirmButton:!0,confirmButtonText:"Done",buttonsStyling:!1,width:"500px",padding:"1.5rem",customClass:{popup:"rounded-3",confirmButton:"btn btn-primary btn-lg w-100"}}).then(()=>{window.location.reload()})}else throw new Error(a.message||"Prepayment failed")}).catch(a=>{console.error("Error processing prepayment:",a),Swal.fire({icon:"error",title:"Prepayment Failed",text:a.message||"An error occurred while processing prepayment",confirmButtonText:"OK"}),s.disabled=!1,s.innerHTML='<i class="icon-base ri ri-check-line me-1"></i> Process Prepayment'})}function m(e,t,n){const r=document.getElementById(e);r.className=`alert alert-${t} mb-4 d-flex align-items-center`;const s=t==="success"?"ri-checkbox-circle-line":t==="danger"?"ri-error-warning-line":"ri-information-line";r.innerHTML=`
            <i class="icon-base ri ${s} me-2"></i>
            <span>${n}</span>
        `}function o(e){return e?parseFloat(e).toLocaleString("en-IN",{minimumFractionDigits:2,maximumFractionDigits:2}):"0.00"}function _(e,t){let n;return function(...s){const c=()=>{clearTimeout(n),e.apply(this,s)};clearTimeout(n),n=setTimeout(c,t)}}})();
