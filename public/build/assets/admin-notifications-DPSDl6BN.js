(function(){if(window.adminNotificationsInitialized)return;window.adminNotificationsInitialized=!0;const N=5e3,w=document.documentElement.getAttribute("data-base-url"),r=window.baseUrl||(w?w.replace(/\/+$/,"")+"/":"/"),_=r+"assets/audio/notification-sound.mp3";$.ajaxSetup({headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")}});let g=null,c=null,y=null;function A(){c=new Audio(_),c.volume=1;const t=function(){c&&c.play().then(function(){c.pause(),c.currentTime=0,document.removeEventListener("click",t),document.removeEventListener("keydown",t),console.log("Notification audio unlocked successfully.")}).catch(function(s){console.log("Audio unlock failed:",s)})};document.addEventListener("click",t),document.addEventListener("keydown",t)}function T(){c&&(c.currentTime=0,c.play().catch(function(t){console.log("Could not play notification sound:",t)}))}function d(){$.ajax({url:r+"admin/notifications/latest",type:"GET",data:{limit:4},success:function(t){t.success&&(C(t.notifications,t.unread_count),g!==null&&t.unread_count>g&&T(),g=t.unread_count)},error:function(t,s,e){console.error("Failed to load notifications:",e)}})}function C(t,s){const e=$("#notificationBadge"),l=$("#notificationPulse"),n=$("#notificationCount");s>0?(e.show(),l.show(),n.text(s),s>0?$("#notificationDropdown").addClass("has-unread"):$("#notificationDropdown").removeClass("has-unread")):(e.hide(),l.hide(),n.text("0"),$("#notificationDropdown").removeClass("has-unread"));const o=$("#notificationList");if(o.empty(),t.length===0){o.html(`
                <div class="py-5 px-5 text-center text-body-secondary">
                    <span class="d-flex justify-content-center align-items-center mb-3">
                        <span class="avatar-initial rounded-circle bg-label-secondary">
                            <i class="icon-base ri ri-notification-off-line icon-22px"></i>
                        </span>
                    </span>
                    <p class="mb-0">No notifications yet</p>
                </div>
            `);return}t.forEach(function(a){const i=a.is_read,f=i?"":"bg-label-primary",m=$(`
                <div class="list-group-item list-group-item-action ${f} notification-item" 
                     data-id="${a.id}" 
                     data-link="${a.link||""}"
                     data-badge-color="${a.badge_color}"
                     data-icon="${a.icon}"
                     data-type="${a.type||""}"
                     data-title="${a.title}"
                     data-message="${a.message}"
                     data-created-at="${a.created_at}"
                     data-created-at-formatted="${a.created_at_formatted||""}"
                     data-is-read="${a.is_read?"1":"0"}"
                     style="cursor: pointer;">
                    <div class="d-flex align-items-start p-2">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-initial rounded-circle bg-label-${a.badge_color}">
                                <i class="${a.icon} icon-20px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="mb-0 text-truncate" style="max-width: 200px;">${a.title}</h6>
                                <small class="text-muted text-nowrap ms-2">${a.created_at}</small>
                            </div>
                            <p class="mb-0 small text-truncate">${a.message}</p>
                            ${i?"":'<span class="badge bg-primary badge-sm mt-1">New</span>'}
                        </div>
                    </div>
                </div>
            `);m.on("click",function(){var h=$(this).data("id"),M=$(this).data("link"),S=$(this).data("badge-color"),F=$(this).data("icon"),O=$(this).data("type"),D=$(this).data("title"),j=$(this).data("message"),P=$(this).data("created-at"),B=$(this).data("created-at-formatted"),z=$(this).data("is-read");p({id:h,link:M,badge_color:S,icon:F,type:O,title:D,message:j,created_at:P,created_at_formatted:B,is_read:z})}),o.append(m)})}function p(t){!t.is_read&&t.is_read!=="1"&&E(t.id);var s={danger:{bg:"#ff4c51",text:"#fff"},success:{bg:"#71dd37",text:"#fff"},primary:{bg:"#696cff",text:"#fff"},warning:{bg:"#ffab00",text:"#fff"},info:{bg:"#03c3ec",text:"#fff"},secondary:{bg:"#a8aaae",text:"#fff"}},e=s[t.badge_color]||s.secondary,l={emi_overdue:"EMI Overdue Alert",new_loan_application:"New Loan Application",new_user_registration:"New Registration",payment_received:"Payment Received"},n=t.type||"",o=l[n]||(n?n.replace(/_/g," ").replace(/\b\w/g,function(h){return h.toUpperCase()}):"Notification"),a=$("#notifModalIcon");a.html('<i class="'+(t.icon||"ri-notification-3-line")+'"></i>').css({"background-color":e.bg,color:e.text}),$("#notifModalType").text(o),$("#notifModalTitle").text(t.title||""),$("#notifModalMessage").text(t.message||""),$("#notifModalTime").text(t.created_at||""),$("#notifModalExactTime").text(t.created_at_formatted||"");var i=$("#notifModalGoBtn");t.link?i.attr("href",t.link).removeClass("d-none"):i.addClass("d-none").attr("href","#");var f=document.getElementById("notificationDetailModal");if(f&&typeof bootstrap<"u"){var m=bootstrap.Modal.getOrCreateInstance(f);m.show()}}window.showNotificationModal=p;function E(t,s){$.ajax({url:r+`admin/notifications/${t}/mark-read`,type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(e){e.success&&d()},error:function(){console.error("Failed to mark notification as read")}})}function x(){$.ajax({url:r+"admin/notifications/mark-all-read",type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(t){t.success&&(console.log("Mark all as read successful"),d(),$("#allNotificationsList").length>0&&typeof window.loadAllNotifications=="function"&&(console.log("Refreshing full page notifications in 300ms..."),setTimeout(function(){window.loadAllNotifications()},300)),typeof window.showAlertToast=="function"?showAlertToast("success",t.message):u("success",t.message))},error:function(){u("error","Failed to mark all as read")}})}function k(){$.ajax({url:r+"admin/notifications/clear-read",type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(t){t.success&&(console.log("Clear read notifications successful"),d(),$("#allNotificationsList").length>0&&typeof window.loadAllNotifications=="function"&&(console.log("Refreshing full page notifications in 300ms..."),setTimeout(function(){window.loadAllNotifications()},300)),typeof window.showAlertToast=="function"?showAlertToast("success","All notifications cleared"):u("success","All notifications cleared"))},error:function(){u("error","Failed to clear notifications")}})}function u(t,s){const e=document.querySelector(".toast-container")||v(),l="toast-"+Date.now();let n,o;t==="success"?(n="ri-check-line",o="bg-success"):t==="danger"||t==="error"?(n="ri-close-circle-line",o="bg-danger"):t==="warning"?(n="ri-alert-line",o="bg-warning"):t==="info"?(n="ri-information-line",o="bg-info"):(n="ri-error-warning-line",o="bg-danger");const a=`
            <div id="${l}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
                <div class="toast-header ${o} text-white rounded-5 border-0">
                    <i class="icon-base ${n} me-2"></i>
                    <div class="me-auto fw-medium">${s}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;e.insertAdjacentHTML("beforeend",a);const i=document.getElementById(l);new bootstrap.Toast(i,{autohide:!0,delay:3e3}).show(),i.addEventListener("hidden.bs.toast",function(){i.remove()})}$(document).ready(function(){A(),d(),y||(y=setInterval(d,N)),$("#markAllRead").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),x()}),$("#markAllReadBtn").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),x()}),$("#clearAllNotifications").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),k()}),$("#clearReadBtn").off("click").on("click",function(t){t.preventDefault(),t.stopPropagation(),k()}),$("#notificationDropdown").off("click.notifications").on("click.notifications",function(){d()}),$("#allNotificationsList").length>0&&I()});let b=null;function I(){console.log("Initializing full page notifications..."),loadAllNotifications(),b=setInterval(function(){console.log("Auto-refreshing notifications..."),loadAllNotifications()},3e4),$(window).on("beforeunload",function(){b&&clearInterval(b)})}window.loadAllNotifications=function(){console.log("Loading all notifications..."),$.ajax({url:r+"admin/notifications/latest",type:"GET",data:{limit:50},success:function(t){console.log("Notifications loaded:",t),t.success&&R(t.notifications)},error:function(){console.error("Failed to load notifications"),$("#allNotificationsList").html('<div class="text-center py-4 text-danger">Failed to load notifications</div>')}})};function R(t){console.log("renderAllNotifications called with",t.length,"notifications");const s=$("#allNotificationsList");if(console.log("Container found:",s.length>0),s.empty(),t.length===0){s.html(`
                <div class="text-center py-5 text-body-secondary">
                    <i class="ri-notification-off-line" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0">No notifications yet</p>
                </div>
            `),console.log("No notifications - showing empty state");return}console.log("Rendering",t.length,"notifications..."),t.forEach(function(e){const l=e.is_read,n=l?"":"bg-label-primary",o=$(`
                <div class="list-group-item list-group-item-action ${n} notification-item"
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
                     data-is-read="${l?"1":"0"}">
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
                                ${l?'<span class="text-success small"><i class="ri-check-double-line"></i> Read</span>':'<button class="btn btn-xs btn-label-primary mark-read-btn" data-id="'+e.id+'"><i class="ri-check-line"></i> Mark as Read</button>'}
                            </div>
                        </div>
                    </div>
                </div>
            `);o.on("click",function(a){if(!$(a.target).closest(".mark-read-btn").length){var i=$(this);p({id:i.data("id"),link:i.data("link"),badge_color:i.data("badge-color"),icon:i.data("icon"),type:i.data("type"),title:i.data("title"),message:i.data("message"),created_at:i.data("created-at"),created_at_formatted:i.data("created-at-formatted"),is_read:i.data("is-read")})}}),o.find(".mark-read-btn").on("click",function(a){a.preventDefault(),a.stopPropagation(),L(e.id)}),s.append(o)}),console.log("Finished rendering all notifications")}function L(t){$.ajax({url:r+`admin/notifications/${t}/mark-read`,type:"POST",headers:{"X-CSRF-TOKEN":$('meta[name="csrf-token"]').attr("content")},success:function(s){s.success&&loadAllNotifications()}})}window.showAlertToast=function(t,s){const e=document.querySelector(".toast-container")||v(),l="toast-"+Date.now();let n,o;t==="success"?(n="ri-check-line",o="bg-success"):t==="danger"||t==="error"?(n="ri-close-circle-line",o="bg-danger"):t==="warning"?(n="ri-alert-line",o="bg-warning"):t==="info"?(n="ri-information-line",o="bg-info"):(n="ri-error-warning-line",o="bg-danger");const a=`
            <div id="${l}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
                <div class="toast-header ${o} text-white rounded-5 border-0">
                    <i class="icon-base ${n} me-2"></i>
                    <div class="me-auto fw-medium">${s}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;e.insertAdjacentHTML("beforeend",a);const i=document.getElementById(l);new bootstrap.Toast(i,{autohide:!0,delay:3e3}).show(),i.addEventListener("hidden.bs.toast",function(){i.remove()})};function v(){const t=document.createElement("div");return t.className="toast-container position-fixed top-0 end-0 p-3",t.style.zIndex="9999",document.body.appendChild(t),t}})();
