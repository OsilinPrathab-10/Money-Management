/**
 * Admin Notifications Handler
 * Handles real-time notification updates, sound alerts, and UI interactions
 */

(function () {
    'use strict';

    // Prevent multiple initializations
    if (window.adminNotificationsInitialized) {
        return;
    }
    window.adminNotificationsInitialized = true;

    // Configuration
    const NOTIFICATION_CHECK_INTERVAL = 5000; // 5 seconds
    const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
    const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl.replace(/\/+$/, '') + '/' : '/');
    const NOTIFICATION_SOUND_PATH = baseUrl + 'assets/audio/notification-sound.mp3';

    // Global AJAX Setup for CSRF
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    let lastNotificationCount = null;
    let notificationSound = null;
    let intervalId = null;

    // Initialize notification sound
    function initNotificationSound() {
        notificationSound = new Audio(NOTIFICATION_SOUND_PATH);
        notificationSound.volume = 1; // 100% volume
        
        // Unlock audio context on first user interaction to satisfy browser autoplay policy
        const unlockAudio = function () {
            if (notificationSound) {
                notificationSound.play().then(function () {
                    notificationSound.pause();
                    notificationSound.currentTime = 0;
                    document.removeEventListener('click', unlockAudio);
                    document.removeEventListener('keydown', unlockAudio);
                    console.log('Notification audio unlocked successfully.');
                }).catch(function (e) {
                    console.log('Audio unlock failed:', e);
                });
            }
        };
        document.addEventListener('click', unlockAudio);
        document.addEventListener('keydown', unlockAudio);
    }

    // Play notification sound
    function playNotificationSound() {
        if (notificationSound) {
            notificationSound.currentTime = 0;
            notificationSound.play().catch(function (error) {
                console.log('Could not play notification sound:', error);
            });
        }
    }

    // Load latest notifications
    function loadNotifications() {
        $.ajax({
            url: baseUrl + 'admin/notifications/latest',
            type: 'GET',
            data: { limit: 4 }, // Show minimum 4 notifications
            success: function (response) {
                if (response.success) {
                    updateNotificationUI(response.notifications, response.unread_count);

                    // Play sound if new notifications arrived
                    if (lastNotificationCount !== null && response.unread_count > lastNotificationCount) {
                        playNotificationSound();
                    }

                    lastNotificationCount = response.unread_count;
                }
            },
            error: function (xhr, status, error) {
                console.error('Failed to load notifications:', error);
            }
        });
    }

    // Update notification UI
    function updateNotificationUI(notifications, unreadCount) {
        // Update badge and pulse effect
        const badge = $('#notificationBadge');
        const pulse = $('#notificationPulse');
        const countBadge = $('#notificationCount');

        if (unreadCount > 0) {
            badge.show();
            pulse.show();
            countBadge.text(unreadCount);

            // Add/remove pulse class based on unread count
            if (unreadCount > 0) {
                $('#notificationDropdown').addClass('has-unread');
            } else {
                $('#notificationDropdown').removeClass('has-unread');
            }
        } else {
            badge.hide();
            pulse.hide();
            countBadge.text('0');
            $('#notificationDropdown').removeClass('has-unread');
        }

        // Update notification list
        const notificationList = $('#notificationList');
        notificationList.empty();

        if (notifications.length === 0) {
            notificationList.html(`
                <div class="py-5 px-5 text-center text-body-secondary">
                    <span class="d-flex justify-content-center align-items-center mb-3">
                        <span class="avatar-initial rounded-circle bg-label-secondary">
                            <i class="icon-base ri ri-notification-off-line icon-22px"></i>
                        </span>
                    </span>
                    <p class="mb-0">No notifications yet</p>
                </div>
            `);
            return;
        }

        notifications.forEach(function (notification) {
            const isRead = notification.is_read;
            const bgClass = isRead ? '' : 'bg-label-primary';

            const item = $(`
                <div class="list-group-item list-group-item-action ${bgClass} notification-item" 
                     data-id="${notification.id}" 
                     data-link="${notification.link || ''}"
                     data-badge-color="${notification.badge_color}"
                     data-icon="${notification.icon}"
                     data-type="${notification.type || ''}"
                     data-title="${notification.title}"
                     data-message="${notification.message}"
                     data-created-at="${notification.created_at}"
                     data-created-at-formatted="${notification.created_at_formatted || ''}"
                     data-is-read="${notification.is_read ? '1' : '0'}"
                     style="cursor: pointer;">
                    <div class="d-flex align-items-start p-2">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-initial rounded-circle bg-label-${notification.badge_color}">
                                <i class="${notification.icon} icon-20px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="mb-0 text-truncate" style="max-width: 200px;">${notification.title}</h6>
                                <small class="text-muted text-nowrap ms-2">${notification.created_at}</small>
                            </div>
                            <p class="mb-0 small text-truncate">${notification.message}</p>
                            ${!isRead ? '<span class="badge bg-primary badge-sm mt-1">New</span>' : ''}
                        </div>
                    </div>
                </div>
            `);

            // Click handler for notification item - open detail modal
            item.on('click', function () {
                var notificationId = $(this).data('id');
                var link = $(this).data('link');
                var badgeColor = $(this).data('badge-color');
                var icon = $(this).data('icon');
                var type = $(this).data('type');
                var title = $(this).data('title');
                var message = $(this).data('message');
                var createdAt = $(this).data('created-at');
                var createdAtFormatted = $(this).data('created-at-formatted');
                var isRead = $(this).data('is-read');

                showNotificationModal({
                    id: notificationId,
                    link: link,
                    badge_color: badgeColor,
                    icon: icon,
                    type: type,
                    title: title,
                    message: message,
                    created_at: createdAt,
                    created_at_formatted: createdAtFormatted,
                    is_read: isRead,
                });
            });

            notificationList.append(item);
        });
    }

    // ==========================================
    // NOTIFICATION DETAIL MODAL
    // ==========================================

    function showNotificationModal(notification) {
        // Mark as read silently
        if (!notification.is_read && notification.is_read !== '1') {
            markAsRead(notification.id, function() {});
        }

        // Color map for badge types
        var colorMap = {
            'danger':    { bg: '#ff4c51', text: '#fff' },
            'success':   { bg: '#71dd37', text: '#fff' },
            'primary':   { bg: '#696cff', text: '#fff' },
            'warning':   { bg: '#ffab00', text: '#fff' },
            'info':      { bg: '#03c3ec', text: '#fff' },
            'secondary': { bg: '#a8aaae', text: '#fff' },
        };
        var color = colorMap[notification.badge_color] || colorMap['secondary'];

        // Type label map
        var typeLabels = {
            'emi_overdue': 'EMI Overdue Alert',
            'new_loan_application': 'New Loan Application',
            'new_user_registration': 'New Registration',
            'payment_received': 'Payment Received',
        };
        var rawType = notification.type || '';
        var typeLabel = typeLabels[rawType] || (rawType ? rawType.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }) : 'Notification');

        // Populate modal elements
        var $icon = $('#notifModalIcon');
        $icon.html('<i class="' + (notification.icon || 'ri-notification-3-line') + '"></i>')
             .css({ 'background-color': color.bg, 'color': color.text });

        $('#notifModalType').text(typeLabel);
        $('#notifModalTitle').text(notification.title || '');
        $('#notifModalMessage').text(notification.message || '');
        $('#notifModalTime').text(notification.created_at || '');
        $('#notifModalExactTime').text(notification.created_at_formatted || '');

        var $goBtn = $('#notifModalGoBtn');
        if (notification.link) {
            $goBtn.attr('href', notification.link).removeClass('d-none');
        } else {
            $goBtn.addClass('d-none').attr('href', '#');
        }

        // Open the Bootstrap modal
        var modalEl = document.getElementById('notificationDetailModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }
    }

    // Expose globally so the full-page notifications list can also use it
    window.showNotificationModal = showNotificationModal;

    // Mark notification as read
    function markAsRead(notificationId, callback) {
        $.ajax({
            url: baseUrl + `admin/notifications/${notificationId}/mark-read`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.success) {
                    loadNotifications();
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            },
            error: function () {
                console.error('Failed to mark notification as read');
            }
        });
    }

    // Mark all notifications as read
    function markAllAsRead() {
        $.ajax({
            url: baseUrl + 'admin/notifications/mark-all-read',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.success) {
                    console.log('Mark all as read successful');
                    loadNotifications();
                    // Also refresh full page list if on notifications page
                    if ($('#allNotificationsList').length > 0 && typeof window.loadAllNotifications === 'function') {
                        console.log('Refreshing full page notifications in 300ms...');
                        setTimeout(function () {
                            window.loadAllNotifications();
                        }, 300);
                    }
                    // Use showAlertToast if available, otherwise showToast
                    if (typeof window.showAlertToast === 'function') {
                        showAlertToast('success', response.message);
                    } else {
                        showToast('success', response.message);
                    }
                }
            },
            error: function () {
                showToast('error', 'Failed to mark all as read');
            }
        });
    }

    // Clear all notifications
    function clearAllNotifications() {
        $.ajax({
            url: baseUrl + 'admin/notifications/clear-read',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.success) {
                    console.log('Clear read notifications successful');
                    loadNotifications();
                    // Also refresh full page list if on notifications page
                    if ($('#allNotificationsList').length > 0 && typeof window.loadAllNotifications === 'function') {
                        console.log('Refreshing full page notifications in 300ms...');
                        setTimeout(function () {
                            window.loadAllNotifications();
                        }, 300);
                    }
                    // Use showAlertToast if available, otherwise showToast
                    if (typeof window.showAlertToast === 'function') {
                        showAlertToast('success', 'All notifications cleared');
                    } else {
                        showToast('success', 'All notifications cleared');
                    }
                }
            },
            error: function () {
                showToast('error', 'Failed to clear notifications');
            }
        });
    }

    // Show toast notification
    function showToast(type, message) {
        const toastContainer = document.querySelector('.toast-container') || createToastContainer();
        const toastId = 'toast-' + Date.now();
        let iconClass, bgClass;

        // Map alert types to toast styles
        if (type === 'success') {
            iconClass = 'ri-check-line';
            bgClass = 'bg-success';
        } else if (type === 'danger' || type === 'error') {
            iconClass = 'ri-close-circle-line';
            bgClass = 'bg-danger';
        } else if (type === 'warning') {
            iconClass = 'ri-alert-line';
            bgClass = 'bg-warning';
        } else if (type === 'info') {
            iconClass = 'ri-information-line';
            bgClass = 'bg-info';
        } else {
            iconClass = 'ri-error-warning-line';
            bgClass = 'bg-danger';
        }

        // Create toast with rounded corners and shadow
        const toastHTML = `
            <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
                <div class="toast-header ${bgClass} text-white rounded-5 border-0">
                    <i class="icon-base ${iconClass} me-2"></i>
                    <div class="me-auto fw-medium">${message}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 3000
        });

        toast.show();

        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    // Initialize on document ready
    $(document).ready(function () {
        // Initialize notification sound
        initNotificationSound();

        // Load notifications immediately
        loadNotifications();

        // Set up periodic refresh (only once)
        if (!intervalId) {
            intervalId = setInterval(loadNotifications, NOTIFICATION_CHECK_INTERVAL);
        }

        // Mark all as read button (dropdown)
        $('#markAllRead').off('click').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            markAllAsRead();
        });

        // Mark all as read button (full page)
        $('#markAllReadBtn').off('click').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            markAllAsRead();
        });

        // Clear all notifications button (dropdown)
        $('#clearAllNotifications').off('click').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            clearAllNotifications();
        });

        // Clear all notifications button (full page)
        $('#clearReadBtn').off('click').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            clearAllNotifications();
        });

        // Refresh notifications when dropdown is opened (use one-time event binding)
        $('#notificationDropdown').off('click.notifications').on('click.notifications', function () {
            loadNotifications();
        });

        // Initialize full page notifications if on notifications page
        if ($('#allNotificationsList').length > 0) {
            initializeFullPageNotifications();
        }
    });

    // ==========================================
    // FULL PAGE NOTIFICATIONS FUNCTIONS
    // ==========================================

    let notificationRefreshInterval = null;

    function initializeFullPageNotifications() {
        console.log('Initializing full page notifications...');

        // Load notifications immediately
        loadAllNotifications();

        // Auto-refresh notifications every 30 seconds for real-time updates
        notificationRefreshInterval = setInterval(function () {
            console.log('Auto-refreshing notifications...');
            loadAllNotifications();
        }, 30000); // 30 seconds

        // Clean up interval when leaving the page
        $(window).on('beforeunload', function () {
            if (notificationRefreshInterval) {
                clearInterval(notificationRefreshInterval);
            }
        });
    }

    // Load all notifications for full page
    window.loadAllNotifications = function () {
        console.log('Loading all notifications...');
        $.ajax({
            url: baseUrl + 'admin/notifications/latest',
            type: 'GET',
            data: { limit: 50 },
            success: function (response) {
                console.log('Notifications loaded:', response);
                if (response.success) {
                    renderAllNotifications(response.notifications);
                }
            },
            error: function () {
                console.error('Failed to load notifications');
                $('#allNotificationsList').html('<div class="text-center py-4 text-danger">Failed to load notifications</div>');
            }
        });
    };

    // Render all notifications in the full page list
    function renderAllNotifications(notifications) {
        console.log('renderAllNotifications called with', notifications.length, 'notifications');
        const container = $('#allNotificationsList');
        console.log('Container found:', container.length > 0);
        container.empty();

        if (notifications.length === 0) {
            container.html(`
                <div class="text-center py-5 text-body-secondary">
                    <i class="ri-notification-off-line" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0">No notifications yet</p>
                </div>
            `);
            console.log('No notifications - showing empty state');
            return;
        }

        console.log('Rendering', notifications.length, 'notifications...');
        notifications.forEach(function (notification) {
            const isRead = notification.is_read;
            const bgClass = isRead ? '' : 'bg-label-primary';

            const item = $(`
                <div class="list-group-item list-group-item-action ${bgClass} notification-item"
                     style="cursor:pointer;"
                     data-id="${notification.id}"
                     data-link="${notification.link || ''}"
                     data-badge-color="${notification.badge_color}"
                     data-icon="${notification.icon}"
                     data-type="${notification.type || ''}"
                     data-title="${notification.title}"
                     data-message="${notification.message}"
                     data-created-at="${notification.created_at}"
                     data-created-at-formatted="${notification.created_at_formatted || ''}"
                     data-is-read="${isRead ? '1' : '0'}">
                    <div class="d-flex align-items-start py-2">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-initial rounded-circle bg-label-${notification.badge_color}">
                                <i class="${notification.icon} icon-20px"></i>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-1">${notification.title}</h6>
                                <small class="text-muted">${notification.created_at}</small>
                            </div>
                            <p class="mb-1 small text-truncate" style="max-width:460px;">${notification.message}</p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="badge bg-label-${notification.badge_color} small"><i class="ri-eye-line me-1"></i>View Details</span>
                                ${!isRead ? '<button class="btn btn-xs btn-label-primary mark-read-btn" data-id="' + notification.id + '"><i class="ri-check-line"></i> Mark as Read</button>' : '<span class="text-success small"><i class="ri-check-double-line"></i> Read</span>'}
                            </div>
                        </div>
                    </div>
                </div>
            `);

            // Open detail modal on row click
            item.on('click', function(e) {
                if ($(e.target).closest('.mark-read-btn').length) return; // let mark-read button handle itself
                var n = $(this);
                showNotificationModal({
                    id: n.data('id'),
                    link: n.data('link'),
                    badge_color: n.data('badge-color'),
                    icon: n.data('icon'),
                    type: n.data('type'),
                    title: n.data('title'),
                    message: n.data('message'),
                    created_at: n.data('created-at'),
                    created_at_formatted: n.data('created-at-formatted'),
                    is_read: n.data('is-read'),
                });
            });

            item.find('.mark-read-btn').on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                markAsReadSingle(notification.id);
            });

            container.append(item);
        });
        console.log('Finished rendering all notifications');
    }

    // Mark single notification as read (for full page)
    function markAsReadSingle(id) {
        $.ajax({
            url: baseUrl + `admin/notifications/${id}/mark-read`,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.success) {
                    loadAllNotifications();
                }
            }
        });
    }

    // Show alert toast (used by full page button handlers)
    window.showAlertToast = function (type, message) {
        const toastContainer = document.querySelector('.toast-container') || createToastContainer();
        const toastId = 'toast-' + Date.now();
        let iconClass, bgClass;

        // Map alert types to toast styles
        if (type === 'success') {
            iconClass = 'ri-check-line';
            bgClass = 'bg-success';
        } else if (type === 'danger' || type === 'error') {
            iconClass = 'ri-close-circle-line';
            bgClass = 'bg-danger';
        } else if (type === 'warning') {
            iconClass = 'ri-alert-line';
            bgClass = 'bg-warning';
        } else if (type === 'info') {
            iconClass = 'ri-information-line';
            bgClass = 'bg-info';
        } else {
            iconClass = 'ri-error-warning-line';
            bgClass = 'bg-danger';
        }

        // Create toast with rounded corners and shadow
        const toastHTML = `
            <div id="${toastId}" class="bs-toast toast fade show rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
                <div class="toast-header ${bgClass} text-white rounded-5 border-0">
                    <i class="icon-base ${iconClass} me-2"></i>
                    <div class="me-auto fw-medium">${message}</div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 3000
        });

        toast.show();

        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove();
        });
    };

    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

})();
