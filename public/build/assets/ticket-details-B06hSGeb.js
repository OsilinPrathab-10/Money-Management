$(function(){const l=$("#chatContainer");l.length&&l.scrollTop(l[0].scrollHeight),$("#replyAttachments").on("change",function(){const e=this.files,i=$("#filePreviewContainer"),c=$("#fileSelectedText");i.empty(),e&&e.length>0?(i.removeClass("d-none"),c.text(`${e.length} file(s) attached`),Array.from(e).forEach(function(o){const s=(o.size/1024).toFixed(0),t=`
          <span class="badge bg-label-primary d-inline-flex align-items-center gap-1 py-1 px-2">
            <i class="icon-base ri ri-attachment-2"></i>
            <span class="text-truncate" style="max-width: 160px;">${d(o.name)}</span>
            <small class="opacity-75">(${s}KB)</small>
          </span>
        `;i.append(t)})):(i.addClass("d-none"),c.text("Attach file"))}),$("#closeTicketBtn").on("click",function(){new bootstrap.Modal(document.getElementById("closeTicketModal")).show()}),$("#confirmCloseTicket").on("click",function(){const e=$("#ticketId").val();$.ajax({url:baseUrl+"support/tickets/"+e+"/status",type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},data:{status:"closed"},success:function(i){r("success",i.message),bootstrap.Modal.getInstance(document.getElementById("closeTicketModal")).hide(),setTimeout(function(){window.location.reload()},800)},error:function(){r("danger","Failed to close ticket")}})});function d(e){return e?e.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;").replace(/\n/g,"<br>"):""}$("#replyForm").on("submit",function(e){e.preventDefault();const i=new FormData(this),c=$("#ticketId").val(),o=$(this).find('button[type="submit"]'),s=o.html();o.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...').prop("disabled",!0),$.ajax({url:baseUrl+"support/tickets/"+c+"/reply",type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},data:i,processData:!1,contentType:!1,success:function(t){if(o.html(s).prop("disabled",!1),t.success&&t.reply){const a=t.reply;let n="";a.attachments&&a.attachments.length>0&&(n='<div class="mt-2 d-flex flex-wrap gap-1">',a.attachments.forEach(function(p){n+=`
                <a href="${p.url}" target="_blank" class="attachment-item text-decoration-none text-white border rounded px-2 py-1 d-inline-flex align-items-center">
                  <i class="icon-base ri ri-attachment-2 me-1"></i>
                  <span>${d(p.file_name)}</span>
                </a>
              `}),n+="</div>");const m=`
            <div class="chat-message user-message">
              <div class="message-bubble">
                <div class="d-flex align-items-center mb-2">
                  <div class="avatar avatar-sm me-2">
                    <div class="avatar-initial bg-label-light rounded-circle">
                      ${a.user_avatar||"U"}
                    </div>
                  </div>
                  <div>
                    <strong>${d(a.user_name)}</strong>
                    <small class="ms-2 opacity-75">${a.created_at}</small>
                  </div>
                </div>
                <p class="mb-0">${d(a.message)}</p>
                ${n}
              </div>
            </div>
          `;l.append(m),l.animate({scrollTop:l[0].scrollHeight},300),$("#replyForm")[0].reset(),$("#filePreviewContainer").empty().addClass("d-none"),$("#fileSelectedText").text("Attach file"),r("success",t.message)}else r("success",t.message||"Reply sent")},error:function(t){var n;o.html(s).prop("disabled",!1);const a=((n=t.responseJSON)==null?void 0:n.message)||"Failed to send reply";r("danger",a)}})});function r(e,i){const c=document.querySelector(".toast-container")||u(),o="toast-"+Date.now();let s,t;e==="success"?(s="ri-check-line",t="bg-success"):e==="danger"?(s="ri-close-circle-line",t="bg-danger"):e==="warning"?(s="ri-alert-line",t="bg-warning"):e==="info"?(s="ri-information-line",t="bg-info"):(s="ri-error-warning-line",t="bg-danger");const a=`
      <div id="${o}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${t} text-white rounded-5 border-0">
          <i class="icon-base ${s} me-2"></i>
          <div class="me-auto fw-medium">${i}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;c.insertAdjacentHTML("beforeend",a);const n=document.getElementById(o);typeof bootstrap<"u"&&bootstrap.Toast&&(new bootstrap.Toast(n,{autohide:!0,delay:3e3}).show(),n.addEventListener("hidden.bs.toast",function(){n.remove()}))}function u(){const e=document.createElement("div");return e.className="toast-container position-fixed top-0 end-0 p-3",e.style.zIndex="9999",document.body.appendChild(e),e}});
