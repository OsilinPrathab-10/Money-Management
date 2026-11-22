(function(){if(window.adminNotificationsInitialized)return;window.adminNotificationsInitialized=!0;const C=25e3,x=document.documentElement.getAttribute("data-base-url");let l=window.baseUrl||(x?x.replace(/\/+$/,"")+"/":"/");l.endsWith("/")||(l+="/"),window.location.protocol==="https:"&&l.startsWith("http://")&&(l=l.replace(/^http:\/\//i,"https://"));try{if(/^https?:\/\//i.test(l)){const t=new URL(l,window.location.href);(t.protocol!==window.location.protocol||t.host!==window.location.host)&&(l=t.pathname.endsWith("/")?t.pathname:t.pathname+"/")}}catch{}const I=l+"assets/audio/notification-sound.mp3";$.ajaxSetup({headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")}});let h=null,r=null,g=null,f=null,p=!1,w=!1;function N(){p=!0,g&&(clearInterval(g),g=null),f&&(clearInterval(f),f=null)}function _(t){return t&&(t.status===401||t.status===419)}function E(){r=new Audio(I),r.volume=1;const t=function(){r&&r.play().then(function(){r.pause(),r.currentTime=0,document.removeEventListener("click",t),document.removeEventListener("keydown",t),console.log("Notification audio unlocked successfully.")}).catch(function(a){console.log("Audio unlock failed:",a)})};document.addEventListener("click",t),document.addEventListener("keydown",t)}function L(){r&&(r.currentTime=0,r.play().catch(function(t){console.log("Could not play notification sound:",t)}))}function d(){p||w||(w=!0,$.ajax({url:l+"admin/notifications/latest",type:"GET",data:{limit:4},timeout:1e4,success:function(t){if(t&&t.success){if(R(t.notifications,t.unread_count),h!==null&&t.unread_count>h){L();const a=(t.notifications||[])[0];a&&a.title&&u("info",a.title)}h=t.unread_count}},error:function(t,a){if(_(t)){N();return}a!=="timeout"&&a!=="abort"&&console.warn("Failed to load notifications")},complete:function(){w=!1}}))}function R(t,a){const e=$("#notificationBadge"),c=$("#notificationPulse"),s=$("#notificationCount");a>0?(e.show(),c.show(),s.text(a),a>0?$("#notificationDropdown").addClass("has-unread"):$("#notificationDropdown").removeClass("has-unread")):(e.hide(),c.hide(),s.text("0"),$("#notificationDropdown").removeClass("has-unread"));const n=$("#notificationList");if(n.empty(),t.length===0){n.html(`
                <div class="py-5 px-5 text-center text-body-secondary">
                    <span class="d-flex justify-content-center align-items-center mb-3">
                        <span class="avatar-initial rounded-circle bg-label-secondary">
                            <i class="icon-base ri ri-notification-off-line icon-22px"></i>
                        </span>
                    </span>
                    <p class="mb-0">No notifications yet</p>
                </div>
            `);return}t.forEach(function(i){const o=i.is_read,m=o?"":"bg-label-primary",b=$(`
                <div class="list-group-item list-group-item-action ${m} notification-item" 
                     data-id="${i.id}" 
                     data-link="${i.link||""}"
                     data-badge-color="${i.badge_color}"
                     data-icon="${i.icon}"
                     data-type="${i.type||""}"
                     data-title="${i.title}"
                     data-message="${i.message}"
                     data-created-at="${i.created_at}"
                     data-created-at-formatted="${i.created_at_formatted||""}"
                     data-is-read="${i.is_read?"1":"0"}"
                     style="cursor: pointer;">
                    <div class="d-flex align-items-start p-2">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-initial rounded-circle bg-label-${i.badge_color}">
                                <i class="${i.icon} icon-20px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="mb-0 text-truncate" style="max-width: 200px;">${i.title}</h6>
                                <small class="text-muted text-nowrap ms-2">${i.created_at}</small>
                            </div>
                            <p class="mb-0 small text-truncate">${i.message}</p>
                            ${o?"":'<span class="badge bg-primary badge-sm mt-1">New</span>'}
                        </div>
                    </div>
                </div>
            `);b.on("click",function(){var k=$(this).data("id"),P=$(this).data("link"),D=$(this).data("badge-color"),j=$(this).data("icon"),B=$(this).data("type"),U=$(this).data("title"),z=$(this).data("message"),H=$(this).data("created-at"),K=$(this).data("created-at-formatted"),X=$(this).data("is-read");v({id:k,link:P,badge_color:D,icon:j,type:B,title:U,message:z,created_at:H,created_at_formatted:K,is_read:X})}),n.append(b)})}function v(t){!t.is_read&&t.is_read!=="1"&&M(t.id);var a={danger:{bg:"#ff4c51",text:"#fff"},success:{bg:"#71dd37",text:"#fff"},primary:{bg:"#696cff",text:"#fff"},warning:{bg:"#ffab00",text:"#fff"},info:{bg:"#03c3ec",text:"#fff"},secondary:{bg:"#a8aaae",text:"#fff"}},e=a[t.badge_color]||a.secondary,c={emi_overdue:"EMI Overdue Alert",new_loan_application:"New Loan Application",new_user_registration:"New Registration",payment_received:"Payment Received",broadcast:"Custom Broadcast",interest_update:"Loan Interest Update",loan_product:"New Loan Product",offer:"Offer / Promotion",disbursement:"Application Disbursed",general:"General Announcement"},s=t.type||"",n=c[s]||(s?s.replace(/_/g," ").replace(/\b\w/g,function(k){return k.toUpperCase()}):"Notification"),i=$("#notifModalIcon");i.html('<i class="'+(t.icon||"ri-notification-3-line")+'"></i>').css({"background-color":e.bg,color:e.text}),$("#notifModalType").text(n),$("#notifModalTitle").text(t.title||""),$("#notifModalMessage").text(t.message||""),$("#notifModalTime").text(t.created_at||""),$("#notifModalExactTime").text(t.created_at_formatted||"");var o=$("#notifModalGoBtn");t.link?o.attr("href",t.link).removeClass("d-none"):o.addClass("d-none").attr("href","#");var m=document.getElementById("notificationDetailModal");if(m&&typeof bootstrap<"u"){var b=bootstrap.Modal.getOrCreateInstance(m);b.show()}}window.showNotificationModal=v;function M(t,a){$.ajax({url:l+`admin/notifications/${t}/mark-read`,type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(e){e.success&&d()},error:function(){console.error("Failed to mark notification as read")}})}function A(){$.ajax({url:l+"admin/notifications/mark-all-read",type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(t){t.success&&(console.log("Mark all as read successful"),d(),$("#allNotificationsList").length>0&&typeof window.loadAllNotifications=="function"&&(console.log("Refreshing full page notifications in 300ms..."),setTimeout(function(){window.loadAllNotifications()},300)),typeof window.showAlertToast=="function"?showAlertToast("success",t.message):u("success",t.message))},error:function(){u("error","Failed to mark all as read")}})}function T(){$.ajax({url:l+"admin/notifications/clear-read",type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(t){t.success&&(console.log("Clear read notifications successful"),d(),$("#allNotificationsList").length>0&&typeof window.loadAllNotifications=="function"&&(console.log("Refreshing full page notifications in 300ms..."),setTimeout(function(){window.loadAllNotifications()},300)),typeof window.showAlertToast=="function"?showAlertToast("success","All notifications cleared"):u("success","All notifications cleared"))},error:function(){u("error","Failed to clear notifications")}})}function u(t,a){const e=document.querySelector(".toast-container")||y(),c="toast-"+Date.now();let s,n;t==="success"?(s="ri-check-line",n="bg-success"):t==="danger"||t==="error"?(s="ri-close-circle-line",n="bg-danger"):t==="warning"?(s="ri-alert-line",n="bg-warning"):t==="info"?(s="ri-information-line",n="bg-info"):(s="ri-error-warning-line",n="bg-danger");const i=`
            <div id="${c}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
                <div class="toast-header ${n} text-white rounded-5 border-0">
                    <i class="icon-base ${s} me-2"></i>
                    <div class="me-auto fw-medium">${a}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;e.insertAdjacentHTML("beforeend",i);const o=document.getElementById(c);new bootstrap.Toast(o,{autohide:!0,delay:3e3}).show(),o.addEventListener("hidden.bs.toast",function(){o.remove()})}$(document).ready(function(){E(),d(),g||(g=setInterval(d,C)),$("#markAllRead").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),A()}),$("#markAllReadBtn").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),A()}),$("#clearAllNotifications").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),T()}),$("#clearReadBtn").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),T()}),$("#notificationDropdown").off("click.notifications").on("click.notifications",function(){d()}),$("#allNotificationsList").length>0&&S()});function S(){p||(console.log("Initializing full page notifications..."),loadAllNotifications(),f=setInterval(function(){console.log("Auto-refreshing notifications..."),loadAllNotifications()},3e4),$(window).on("beforeunload",function(){f&&clearInterval(f)}))}window.loadAllNotifications=function(){p||(console.log("Loading all notifications..."),$.ajax({url:l+"admin/notifications/latest",type:"GET",data:{limit:50},success:function(t){console.log("Notifications loaded:",t),t.success&&F(t.notifications)},error:function(t){if(_(t)){N();return}console.error("Failed to load notifications"),$("#allNotificationsList").html('<div class="text-center py-4 text-danger">Failed to load notifications</div>')}}))};function F(t){console.log("renderAllNotifications called with",t.length,"notifications");const a=$("#allNotificationsList");if(console.log("Container found:",a.length>0),a.empty(),t.length===0){a.html(`
                <div class="text-center py-5 text-body-secondary">
                    <i class="ri-notification-off-line" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0">No notifications yet</p>
                </div>
            `),console.log("No notifications - showing empty state");return}console.log("Rendering",t.length,"notifications..."),t.forEach(function(e){const c=e.is_read,s=c?"":"bg-label-primary",n=$(`
                <div class="list-group-item list-group-item-action ${s} notification-item"
                     style="cursor:pointer;"
                     data-id="${e.id}"
                     data-link="${e.link||""}"
                     data-badge-color="${e.badge_color}"
                     data-icon="${e.icon}"
                     data-type="${e.type||""}"
                     data-title="${e.title}"
                     data-message="${e.message}"
                     data-created-at="${e.created_at}"
                     data-created-at-formatted="${e.created_at_formatted||""}"
                     data-is-read="${c?"1":"0"}">
                    <div class="d-flex align-items-start py-2">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-initial rounded-circle bg-label-${e.badge_color}">
                                <i class="${e.icon} icon-20px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1">${e.title}</h6>
                                <small class="text-muted">${e.created_at}</small>
                            </div>
                            <p class="mb-1 small text-truncate" style="max-width:460px;">${e.message}</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="badge bg-label-${e.badge_color} small"><i class="ri-eye-line me-1"></i>View Details</span>
                                ${c?'<span class="text-success small"><i class="ri-check-double-line"></i> Read</span>':'<button class="btn btn-xs btn-label-primary mark-read-btn" data-id="'+e.id+'"><i class="ri-check-line"></i> Mark as Read</button>'}
                            </div>
                        </div>
                    </div>
                </div>
            `);n.on("click",function(i){if(!$(i.target).closest(".mark-read-btn").length){var o=$(this);v({id:o.data("id"),link:o.data("link"),badge_color:o.data("badge-color"),icon:o.data("icon"),type:o.data("type"),title:o.data("title"),message:o.data("message"),created_at:o.data("created-at"),created_at_formatted:o.data("created-at-formatted"),is_read:o.data("is-read")})}}),n.find(".mark-read-btn").on("click",function(i){i.preventDefault(),i.stopPropagation(),O(e.id)}),a.append(n)}),console.log("Finished rendering all notifications")}function O(t){$.ajax({url:l+`admin/notifications/${t}/mark-read`,type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(a){a.success&&loadAllNotifications()}})}window.showAlertToast=function(t,a){const e=document.querySelector(".toast-container")||y(),c="toast-"+Date.now();let s,n;t==="success"?(s="ri-check-line",n="bg-success"):t==="danger"||t==="error"?(s="ri-close-circle-line",n="bg-danger"):t==="warning"?(s="ri-alert-line",n="bg-warning"):t==="info"?(s="ri-information-line",n="bg-info"):(s="ri-error-warning-line",n="bg-danger");const i=`
            <div id="${c}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
                <div class="toast-header ${n} text-white rounded-5 border-0">
                    <i class="icon-base ${s} me-2"></i>
                    <div class="me-auto fw-medium">${a}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;e.insertAdjacentHTML("beforeend",i);const o=document.getElementById(c);new bootstrap.Toast(o,{autohide:!0,delay:3e3}).show(),o.addEventListener("hidden.bs.toast",function(){o.remove()})};function y(){const t=document.createElement("div");return t.className="toast-container position-fixed top-0 end-0 p-3",t.style.zIndex="9999",document.body.appendChild(t),t}window.refreshAdminNotifications=d,window.showAdminNotificationToast=u})();
