/**
 * Appointments Admin JavaScript
 * 
 * Handles list actions (view, edit, cancel, etc.) and calendar functionality.
 * 
 * @package AIChat
 * @subpackage Appointments
 * @since 2.3.0
 */
const { __, sprintf } = wp.i18n;

(function($) {
    'use strict';

    const Appointments = {
        config: {},
        calendar: null,
        currentView: 'month',
        currentDate: new Date(),

        init: function() {
            this.config = window.aichat_appointments || {};
            this.bindEvents();
            this.initCalendar();
            this.initSettingsForm();
        },

        bindEvents: function() {
            // View appointment
            $(document).on('click', '.btn-view-appointment', this.viewAppointment.bind(this));
            
            // Edit appointment
            $(document).on('click', '.btn-edit-appointment', this.editAppointment.bind(this));
            
            // Confirm appointment
            $(document).on('click', '.btn-confirm-appointment', this.confirmAppointment.bind(this));
            
            // Complete appointment
            $(document).on('click', '.btn-complete-appointment', this.completeAppointment.bind(this));
            
            // Cancel appointment
            $(document).on('click', '.btn-cancel-appointment', this.cancelAppointment.bind(this));
            
            // No show
            $(document).on('click', '.btn-noshow-appointment', this.noShowAppointment.bind(this));
            
            // Modal save button
            $(document).on('click', '#btn-save-appointment', this.saveAppointment.bind(this));
            
            // Modal status change
            $(document).on('click', '.modal-status-btn', this.changeStatusFromModal.bind(this));
            
            // Calendar navigation
            $(document).on('click', '.calendar-nav-prev', () => this.navigateCalendar(-1));
            $(document).on('click', '.calendar-nav-next', () => this.navigateCalendar(1));
            $(document).on('click', '.calendar-nav-today', () => this.goToToday());
            $(document).on('click', '.calendar-view-btn', this.changeCalendarView.bind(this));
            
            // Working hours - add time slot
            $(document).on('click', '.btn-add-time-slot', this.addTimeSlot.bind(this));
            $(document).on('click', '.btn-remove-time-slot', this.removeTimeSlot.bind(this));
        },

        // =====================
        // LIST ACTIONS
        // =====================

        viewAppointment: function(e) {
            const id = $(e.currentTarget).data('id');
            this.loadAppointmentModal(id, 'view');
        },

        editAppointment: function(e) {
            const id = $(e.currentTarget).data('id');
            this.loadAppointmentModal(id, 'edit');
        },

        loadAppointmentModal: function(id, mode) {
            const self = this;
            
            // Show loading modal
            this.showModal('loading');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'aichat_appointments_get',
                    nonce: this.config.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        self.showAppointmentModal(response.data, mode);
                    } else {
                        self.showToast('error', response.data || __('Error loading appointment', 'axiachat-ai'));
                        self.hideModal();
                    }
                },
                error: function() {
                    self.showToast('error', __('Connection error', 'axiachat-ai'));
                    self.hideModal();
                }
            });
        },

        showAppointmentModal: function(apt, mode) {
            const isEdit = mode === 'edit';
            const dateFormatted = this.formatDate(apt.appointment_date);
            const timeFormatted = apt.start_time + ' - ' + apt.end_time;
            
            const statusBadges = {
                pending: '<span class="badge bg-warning text-dark">' + __('Pending', 'axiachat-ai') + '</span>',
                confirmed: '<span class="badge bg-success">' + __('Confirmed', 'axiachat-ai') + '</span>',
                completed: '<span class="badge bg-secondary">' + __('Completed', 'axiachat-ai') + '</span>',
                cancelled: '<span class="badge bg-danger">' + __('Cancelled', 'axiachat-ai') + '</span>',
                no_show: '<span class="badge bg-dark">' + __('No Show', 'axiachat-ai') + '</span>'
            };
            
            const statusOptions = `
                <option value="pending" ${apt.status === 'pending' ? 'selected' : ''}>⏳ ` + __('Pending', 'axiachat-ai') + `</option>
                <option value="confirmed" ${apt.status === 'confirmed' ? 'selected' : ''}>✓ ` + __('Confirmed', 'axiachat-ai') + `</option>
                <option value="completed" ${apt.status === 'completed' ? 'selected' : ''}>✔ ` + __('Completed', 'axiachat-ai') + `</option>
                <option value="cancelled" ${apt.status === 'cancelled' ? 'selected' : ''}>✗ ` + __('Cancelled', 'axiachat-ai') + `</option>
                <option value="no_show" ${apt.status === 'no_show' ? 'selected' : ''}>👤✗ ` + __('No Show (didn\'t attend)', 'axiachat-ai') + `</option>
            `;
            
            const statusActions = this.getStatusActions(apt.status, apt.id);
            
            const modalContent = `
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-event me-2"></i>
                        ${isEdit ? __('Edit Appointment', 'axiachat-ai') : __('Appointment Details', 'axiachat-ai')}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="` + __('Close', 'axiachat-ai') + `"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block">` + __('Booking Code', 'axiachat-ai') + `</small>
                            <code class="fs-6">${apt.booking_code}</code>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">` + __('Status', 'axiachat-ai') + `</small>
                            ${isEdit ?
                                `<select class="form-select form-select-sm" id="edit-status" style="width: auto; display: inline-block;">
                                    ${statusOptions}
                                </select>` :
                                statusBadges[apt.status] || apt.status
                            }
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">
                                <i class="bi bi-person me-1"></i>` + __('Customer Name', 'axiachat-ai') + `
                            </label>
                            ${isEdit ? 
                                `<input type="text" class="form-control" id="edit-customer-name" value="${this.escapeHtml(apt.customer_name)}">` :
                                `<div class="fw-bold">${this.escapeHtml(apt.customer_name)}</div>`
                            }
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">
                                <i class="bi bi-envelope me-1"></i>` + __('Email', 'axiachat-ai') + `
                            </label>
                            ${isEdit ?
                                `<input type="email" class="form-control" id="edit-customer-email" value="${this.escapeHtml(apt.customer_email)}">` :
                                `<div><a href="mailto:${apt.customer_email}">${this.escapeHtml(apt.customer_email)}</a></div>`
                            }
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">
                                <i class="bi bi-telephone me-1"></i>` + __('Phone', 'axiachat-ai') + `
                            </label>
                            ${isEdit ?
                                `<input type="text" class="form-control" id="edit-customer-phone" value="${this.escapeHtml(apt.customer_phone || '')}">` :
                                `<div>${apt.customer_phone || '—'}</div>`
                            }
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">
                                <i class="bi bi-tag me-1"></i>` + __('Service', 'axiachat-ai') + `
                            </label>
                            ${isEdit ?
                                `<input type="text" class="form-control" id="edit-service" value="${this.escapeHtml(apt.service || '')}">` :
                                `<div>${apt.service || '—'}</div>`
                            }
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">
                                <i class="bi bi-calendar me-1"></i>` + __('Date', 'axiachat-ai') + `
                            </label>
                            ${isEdit ?
                                `<input type="date" class="form-control" id="edit-date" value="${apt.appointment_date}">` :
                                `<div class="fw-bold">${dateFormatted}</div>`
                            }
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">
                                <i class="bi bi-clock me-1"></i>` + __('Time', 'axiachat-ai') + `
                            </label>
                            ${isEdit ?
                                `<div class="row g-2">
                                    <div class="col-6">
                                        <input type="time" class="form-control" id="edit-start-time" value="${apt.start_time}">
                                    </div>
                                    <div class="col-6">
                                        <input type="time" class="form-control" id="edit-end-time" value="${apt.end_time}">
                                    </div>
                                </div>` :
                                `<div>${timeFormatted}</div>`
                            }
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">
                                <i class="bi bi-sticky me-1"></i>` + __('Notes', 'axiachat-ai') + `
                            </label>
                            ${isEdit ?
                                `<textarea class="form-control" id="edit-notes" rows="2">${this.escapeHtml(apt.notes || '')}</textarea>` :
                                `<div class="text-muted">${apt.notes || '—'}</div>`
                            }
                        </div>
                    </div>
                    
                    ${!isEdit ? `
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        ${statusActions}
                    </div>
                    ` : ''}
                    
                    <hr>
                    <div class="row small text-muted">
                        <div class="col-6">
                            <i class="bi bi-clock-history me-1"></i>` + __('Created:', 'axiachat-ai') + ` ${this.formatDateTime(apt.created_at)}
                        </div>
                        <div class="col-6 text-end">
                            <i class="bi bi-pencil me-1"></i>` + __('Updated:', 'axiachat-ai') + ` ${this.formatDateTime(apt.updated_at)}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    ${isEdit ? `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">` + __('Cancel', 'axiachat-ai') + `</button>
                        <button type="button" class="btn btn-primary" id="btn-save-appointment" data-id="${apt.id}">
                            <i class="bi bi-check-lg me-1"></i>` + __('Save Changes', 'axiachat-ai') + `
                        </button>
                    ` : `
                        <button type="button" class="btn btn-outline-primary btn-edit-appointment" data-id="${apt.id}">
                            <i class="bi bi-pencil me-1"></i>` + __('Edit', 'axiachat-ai') + `
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">` + __('Close', 'axiachat-ai') + `</button>
                    `}
                </div>
            `;
            
            this.showModal(modalContent);
        },

        getStatusActions: function(status, id) {
            let actions = '';
            
            if (status === 'pending') {
                actions += `<button class="btn btn-success btn-sm modal-status-btn" data-id="${id}" data-status="confirmed">
                    <i class="bi bi-check-lg me-1"></i>` + __('Confirm', 'axiachat-ai') + `
                </button>`;
            }
            
            if (status === 'confirmed') {
                actions += `<button class="btn btn-secondary btn-sm modal-status-btn" data-id="${id}" data-status="completed">
                    <i class="bi bi-check-circle me-1"></i>` + __('Mark Completed', 'axiachat-ai') + `
                </button>`;
                actions += `<button class="btn btn-dark btn-sm modal-status-btn" data-id="${id}" data-status="no_show">
                    <i class="bi bi-person-x me-1"></i>` + __('No Show', 'axiachat-ai') + `
                </button>`;
            }
            
            if (status !== 'cancelled' && status !== 'completed') {
                actions += `<button class="btn btn-outline-danger btn-sm modal-status-btn" data-id="${id}" data-status="cancelled">
                    <i class="bi bi-x-lg me-1"></i>` + __('Cancel', 'axiachat-ai') + `
                </button>`;
            }
            
            return actions;
        },

        confirmAppointment: function(e) {
            const id = $(e.currentTarget).data('id');
            this.updateStatus(id, 'confirmed', __('Confirm this appointment?', 'axiachat-ai'));
        },

        completeAppointment: function(e) {
            const id = $(e.currentTarget).data('id');
            this.updateStatus(id, 'completed', __('Mark this appointment as completed?', 'axiachat-ai'));
        },

        cancelAppointment: function(e) {
            const id = $(e.currentTarget).data('id');
            if (confirm(__('Are you sure you want to cancel this appointment?', 'axiachat-ai'))) {
                this.updateStatus(id, 'cancelled');
            }
        },

        noShowAppointment: function(e) {
            const id = $(e.currentTarget).data('id');
            this.updateStatus(id, 'no_show', __('Mark customer as no-show?', 'axiachat-ai'));
        },

        changeStatusFromModal: function(e) {
            const id = $(e.currentTarget).data('id');
            const status = $(e.currentTarget).data('status');
            const confirmMsgs = {
                confirmed: __('Confirm this appointment?', 'axiachat-ai'),
                completed: __('Mark as completed?', 'axiachat-ai'),
                cancelled: __('Cancel this appointment?', 'axiachat-ai'),
                no_show: __('Mark as no-show?', 'axiachat-ai')
            };
            
            if (status === 'cancelled') {
                if (!confirm(confirmMsgs[status])) return;
            }
            
            this.updateStatus(id, status);
        },

        updateStatus: function(id, status, confirmMsg) {
            const self = this;
            
            if (confirmMsg && !confirm(confirmMsg)) return;
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'aichat_appointments_update_status',
                    nonce: this.config.nonce,
                    id: id,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', __('Status updated', 'axiachat-ai'));
                        self.hideModal();
                        location.reload();
                    } else {
                        self.showToast('error', response.data);
                    }
                },
                error: function() {
                    self.showToast('error', __('Connection error', 'axiachat-ai'));
                }
            });
        },

        saveAppointment: function(e) {
            const self = this;
            const id = $(e.currentTarget).data('id');
            
            const data = {
                action: 'aichat_appointments_update',
                nonce: this.config.nonce,
                id: id,
                customer_name: $('#edit-customer-name').val(),
                customer_email: $('#edit-customer-email').val(),
                customer_phone: $('#edit-customer-phone').val(),
                service: $('#edit-service').val(),
                appointment_date: $('#edit-date').val(),
                start_time: $('#edit-start-time').val(),
                end_time: $('#edit-end-time').val(),
                notes: $('#edit-notes').val(),
                status: $('#edit-status').val()
            };
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', __('Appointment updated', 'axiachat-ai'));
                        self.hideModal();
                        location.reload();
                    } else {
                        self.showToast('error', response.data);
                    }
                },
                error: function() {
                    self.showToast('error', __('Connection error', 'axiachat-ai'));
                }
            });
        },

        // =====================
        // CALENDAR
        // =====================

        initCalendar: function() {
            const calendarEl = document.getElementById('aichat-appointments-calendar');
            if (!calendarEl || !calendarEl.classList.contains('calendar-container')) return;
            
            this.loadCalendarEvents();
        },

        loadCalendarEvents: function() {
            const self = this;
            const start = this.getCalendarRangeStart();
            const end = this.getCalendarRangeEnd();
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'GET',
                data: {
                    action: 'aichat_appointments_calendar',
                    nonce: this.config.nonce,
                    start: start,
                    end: end
                },
                success: function(events) {
                    self.renderCalendar(events);
                }
            });
        },

        getCalendarRangeStart: function() {
            const d = new Date(this.currentDate);
            if (this.currentView === 'month') {
                d.setDate(1);
                d.setDate(d.getDate() - d.getDay());
            } else {
                d.setDate(d.getDate() - d.getDay());
            }
            return d.toISOString().split('T')[0];
        },

        getCalendarRangeEnd: function() {
            const d = new Date(this.currentDate);
            if (this.currentView === 'month') {
                d.setMonth(d.getMonth() + 1);
                d.setDate(0);
                d.setDate(d.getDate() + (6 - d.getDay()));
            } else {
                d.setDate(d.getDate() + (6 - d.getDay()));
            }
            return d.toISOString().split('T')[0];
        },

        renderCalendar: function(events) {
            const container = $('#aichat-appointments-calendar');
            if (!container.length) return;
            
            const self = this;
            const settings = this.config.settings || {};
            const workingHours = settings.working_hours || {};
            
            // Build calendar HTML based on view
            if (this.currentView === 'month') {
                this.renderMonthView(container, events, workingHours);
            } else if (this.currentView === 'day') {
                this.renderDayView(container, events, workingHours);
            } else {
                this.renderWeekView(container, events, workingHours);
            }
            
            // Bind event clicks
            container.find('.calendar-event').on('click', function() {
                const id = $(this).data('id');
                self.loadAppointmentModal(id, 'view');
            });
        },

        renderMonthView: function(container, events, workingHours) {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDayOfWeek = firstDay.getDay();
            const daysInMonth = lastDay.getDate();
            
            const monthNames = [
                __('January', 'axiachat-ai'), __('February', 'axiachat-ai'), __('March', 'axiachat-ai'),
                __('April', 'axiachat-ai'), __('May', 'axiachat-ai'), __('June', 'axiachat-ai'),
                __('July', 'axiachat-ai'), __('August', 'axiachat-ai'), __('September', 'axiachat-ai'),
                __('October', 'axiachat-ai'), __('November', 'axiachat-ai'), __('December', 'axiachat-ai')
            ];
            const dayNames = [
                __('Sun', 'axiachat-ai'), __('Mon', 'axiachat-ai'), __('Tue', 'axiachat-ai'),
                __('Wed', 'axiachat-ai'), __('Thu', 'axiachat-ai'), __('Fri', 'axiachat-ai'), __('Sat', 'axiachat-ai')
            ];
            
            let html = `
                <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary calendar-nav-prev">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary calendar-nav-today">` + __('Today', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-secondary calendar-nav-next">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <h4 class="mb-0">${monthNames[month]} ${year}</h4>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'month' ? 'active' : ''}" data-view="month">` + __('Month', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'week' ? 'active' : ''}" data-view="week">` + __('Week', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'day' ? 'active' : ''}" data-view="day">` + __('Day', 'axiachat-ai') + `</button>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-weekdays">
                        ${dayNames.map(d => `<div class="calendar-weekday">${d}</div>`).join('')}
                    </div>
                    <div class="calendar-days">
            `;
            
            // Empty cells before first day
            for (let i = 0; i < startDayOfWeek; i++) {
                const prevDate = new Date(year, month, 0 - (startDayOfWeek - i - 1));
                html += `<div class="calendar-day other-month">
                    <div class="day-number">${prevDate.getDate()}</div>
                </div>`;
            }
            
            // Days of month
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const dateStr = date.toISOString().split('T')[0];
                const dayOfWeek = date.getDay();
                const isToday = date.getTime() === today.getTime();
                const isWorkingDay = workingHours[dayOfWeek]?.enabled;
                const dayEvents = events.filter(e => e.start.startsWith(dateStr));
                
                html += `<div class="calendar-day ${isToday ? 'today' : ''} ${!isWorkingDay ? 'non-working' : ''}">
                    <div class="day-number">${day}</div>
                    <div class="day-events">
                        ${dayEvents.slice(0, 3).map(e => `
                            <div class="calendar-event" data-id="${e.id}" style="background-color: ${e.color};">
                                <span class="event-time">${e.start.split('T')[1].substring(0, 5)}</span>
                                <span class="event-title">${this.escapeHtml(e.title)}</span>
                            </div>
                        `).join('')}
                        ${dayEvents.length > 3 ? `<div class="more-events">` + sprintf(
                            /* translators: %s: Number of additional events (e.g., '+3') */
                            __('%s more', 'axiachat-ai'), '+' + (dayEvents.length - 3)) + `</div>` : ''}
                    </div>
                </div>`;
            }
            
            // Empty cells after last day
            const remaining = 7 - ((startDayOfWeek + daysInMonth) % 7);
            if (remaining < 7) {
                for (let i = 1; i <= remaining; i++) {
                    html += `<div class="calendar-day other-month">
                        <div class="day-number">${i}</div>
                    </div>`;
                }
            }
            
            html += '</div></div>';
            
            // Legend
            html += `
                <div class="calendar-legend mt-3 d-flex gap-3 justify-content-center">
                    <div><span class="legend-color" style="background: #ffc107;"></span> ` + __('Pending', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #28a745;"></span> ` + __('Confirmed', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #6c757d;"></span> ` + __('Completed', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #dc3545;"></span> ` + __('Cancelled', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #343a40;"></span> ` + __('No Show', 'axiachat-ai') + `</div>
                </div>
            `;
            
            container.html(html);
        },

        renderWeekView: function(container, events, workingHours) {
            const self = this;
            const startOfWeek = new Date(this.currentDate);
            startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay());
            
            const dayNames = [
                __('Sunday', 'axiachat-ai'), __('Monday', 'axiachat-ai'), __('Tuesday', 'axiachat-ai'),
                __('Wednesday', 'axiachat-ai'), __('Thursday', 'axiachat-ai'), __('Friday', 'axiachat-ai'), __('Saturday', 'axiachat-ai')
            ];
            const monthNames = [
                __('Jan', 'axiachat-ai'), __('Feb', 'axiachat-ai'), __('Mar', 'axiachat-ai'),
                __('Apr', 'axiachat-ai'), __('May', 'axiachat-ai'), __('Jun', 'axiachat-ai'),
                __('Jul', 'axiachat-ai'), __('Aug', 'axiachat-ai'), __('Sep', 'axiachat-ai'),
                __('Oct', 'axiachat-ai'), __('Nov', 'axiachat-ai'), __('Dec', 'axiachat-ai')
            ];
            
            // Generate time slots (8 AM to 8 PM)
            const hours = [];
            for (let h = 8; h <= 20; h++) {
                hours.push(h.toString().padStart(2, '0') + ':00');
            }
            
            let html = `
                <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary calendar-nav-prev">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary calendar-nav-today">` + __('Today', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-secondary calendar-nav-next">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <h4 class="mb-0">` + sprintf(
                        /* translators: 1: Month name, 2: Day number, 3: Year */
                        __('Week of %1$s %2$s, %3$s', 'axiachat-ai'), monthNames[startOfWeek.getMonth()], startOfWeek.getDate(), startOfWeek.getFullYear()) + `</h4>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'month' ? 'active' : ''}" data-view="month">` + __('Month', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'week' ? 'active' : ''}" data-view="week">` + __('Week', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'day' ? 'active' : ''}" data-view="day">` + __('Day', 'axiachat-ai') + `</button>
                    </div>
                </div>
            `;
            
            html += '<div class="week-view-grid">';
            
            // Header row with day names
            html += '<div class="week-header"><div class="time-column"></div>';
            for (let d = 0; d < 7; d++) {
                const date = new Date(startOfWeek);
                date.setDate(date.getDate() + d);
                const isToday = date.toDateString() === new Date().toDateString();
                const isWorkingDay = workingHours[d]?.enabled;
                
                html += `<div class="day-column-header ${isToday ? 'today' : ''} ${!isWorkingDay ? 'non-working' : ''}">
                    <div class="day-name">${dayNames[d]}</div>
                    <div class="day-date">${date.getDate()}</div>
                </div>`;
            }
            html += '</div>';
            
            // Time rows
            html += '<div class="week-body">';
            hours.forEach(hour => {
                html += `<div class="time-row">
                    <div class="time-label">${hour}</div>`;
                
                for (let d = 0; d < 7; d++) {
                    const date = new Date(startOfWeek);
                    date.setDate(date.getDate() + d);
                    const dateStr = date.toISOString().split('T')[0];
                    const isWorkingDay = workingHours[d]?.enabled;
                    
                    // Find events in this hour
                    const hourEvents = events.filter(e => {
                        const eventTime = e.start.split('T')[1]?.substring(0, 5) || '';
                        const eventHour = parseInt(eventTime.split(':')[0]);
                        const slotHour = parseInt(hour.split(':')[0]);
                        return e.start.startsWith(dateStr) && eventHour === slotHour;
                    });
                    
                    html += `<div class="time-cell ${!isWorkingDay ? 'non-working' : ''}">`;
                    hourEvents.forEach(e => {
                        html += `<div class="calendar-event week-event" data-id="${e.id}" style="background-color: ${e.color};">
                            <span class="event-time">${e.start.split('T')[1].substring(0, 5)}</span>
                            <span class="event-title">${this.escapeHtml(e.extendedProps?.status === 'cancelled' ? '✗ ' : '')}${this.escapeHtml(e.title)}</span>
                        </div>`;
                    });
                    html += '</div>';
                }
                
                html += '</div>';
            });
            html += '</div></div>';
            
            // Legend
            html += `
                <div class="calendar-legend mt-3 d-flex gap-3 justify-content-center">
                    <div><span class="legend-color" style="background: #ffc107;"></span> ` + __('Pending', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #28a745;"></span> ` + __('Confirmed', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #6c757d;"></span> ` + __('Completed', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #dc3545;"></span> ` + __('Cancelled', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #343a40;"></span> ` + __('No Show', 'axiachat-ai') + `</div>
                </div>
            `;
            
            container.html(html);
        },

        renderDayView: function(container, events, workingHours) {
            const monthNames = [
                __('January', 'axiachat-ai'), __('February', 'axiachat-ai'), __('March', 'axiachat-ai'),
                __('April', 'axiachat-ai'), __('May', 'axiachat-ai'), __('June', 'axiachat-ai'),
                __('July', 'axiachat-ai'), __('August', 'axiachat-ai'), __('September', 'axiachat-ai'),
                __('October', 'axiachat-ai'), __('November', 'axiachat-ai'), __('December', 'axiachat-ai')
            ];
            const dayNames = [
                __('Sunday', 'axiachat-ai'), __('Monday', 'axiachat-ai'), __('Tuesday', 'axiachat-ai'),
                __('Wednesday', 'axiachat-ai'), __('Thursday', 'axiachat-ai'), __('Friday', 'axiachat-ai'), __('Saturday', 'axiachat-ai')
            ];
            
            const currentDay = new Date(this.currentDate);
            const dayOfWeek = currentDay.getDay();
            const isWorkingDay = workingHours[dayOfWeek]?.enabled;
            const dateStr = currentDay.toISOString().split('T')[0];
            
            let html = `
                <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary calendar-nav-prev">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline-secondary calendar-nav-today">` + __('Today', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-secondary calendar-nav-next">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <h4 class="mb-0">` + sprintf(
                        /* translators: 1: Day name, 2: Month name, 3: Day number, 4: Year */
                        __('%1$s, %2$s %3$s, %4$s', 'axiachat-ai'), dayNames[dayOfWeek], monthNames[currentDay.getMonth()], currentDay.getDate(), currentDay.getFullYear()) + `</h4>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'month' ? 'active' : ''}" data-view="month">` + __('Month', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'week' ? 'active' : ''}" data-view="week">` + __('Week', 'axiachat-ai') + `</button>
                        <button class="btn btn-outline-primary calendar-view-btn ${this.currentView === 'day' ? 'active' : ''}" data-view="day">` + __('Day', 'axiachat-ai') + `</button>
                    </div>
                </div>
            `;
            
            // Time slots for the day (similar to week view but single column)
            const timeSlots = [];
            for (let h = 8; h <= 20; h++) {
                timeSlots.push(`${h.toString().padStart(2, '0')}:00`);
            }
            
            html += '<div class="day-view-grid">';
            
            // Header
            html += `<div class="day-header">
                <div class="time-column"></div>
                <div class="day-column-header full-width ${!isWorkingDay ? 'non-working' : ''}">
                    <div class="day-name">${dayNames[dayOfWeek]}</div>
                    <div class="day-date">${currentDay.getDate()}</div>
                </div>
            </div>`;
            
            // Time rows
            html += '<div class="day-body">';
            timeSlots.forEach(hour => {
                html += `<div class="time-row">
                    <div class="time-label">${hour}</div>`;
                
                // Find events in this hour
                const hourEvents = events.filter(e => {
                    const eventTime = e.start.split('T')[1]?.substring(0, 5) || '';
                    const eventHour = parseInt(eventTime.split(':')[0]);
                    const slotHour = parseInt(hour.split(':')[0]);
                    return e.start.startsWith(dateStr) && eventHour === slotHour;
                });
                
                html += `<div class="time-cell full-width ${!isWorkingDay ? 'non-working' : ''}">`;
                hourEvents.forEach(e => {
                    html += `<div class="calendar-event day-event" data-id="${e.id}" style="background-color: ${e.color};">
                        <span class="event-time">${e.start.split('T')[1].substring(0, 5)}</span>
                        <span class="event-title">${this.escapeHtml(e.extendedProps?.status === 'cancelled' ? '✗ ' : '')}${this.escapeHtml(e.title)}</span>
                        ${e.extendedProps?.customer_email ? `<span class="event-email text-muted small">${this.escapeHtml(e.extendedProps.customer_email)}</span>` : ''}
                    </div>`;
                });
                html += '</div>';
                
                html += '</div>';
            });
            html += '</div></div>';
            
            // Legend
            html += `
                <div class="calendar-legend mt-3 d-flex gap-3 justify-content-center">
                    <div><span class="legend-color" style="background: #ffc107;"></span> ` + __('Pending', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #28a745;"></span> ` + __('Confirmed', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #6c757d;"></span> ` + __('Completed', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #dc3545;"></span> ` + __('Cancelled', 'axiachat-ai') + `</div>
                    <div><span class="legend-color" style="background: #343a40;"></span> ` + __('No Show', 'axiachat-ai') + `</div>
                </div>
            `;
            
            container.html(html);
        },

        navigateCalendar: function(direction) {
            if (this.currentView === 'month') {
                this.currentDate.setMonth(this.currentDate.getMonth() + direction);
            } else if (this.currentView === 'day') {
                this.currentDate.setDate(this.currentDate.getDate() + direction);
            } else {
                this.currentDate.setDate(this.currentDate.getDate() + (direction * 7));
            }
            this.loadCalendarEvents();
        },

        goToToday: function() {
            this.currentDate = new Date();
            this.loadCalendarEvents();
        },

        changeCalendarView: function(e) {
            const view = $(e.currentTarget).data('view');
            this.currentView = view;
            this.loadCalendarEvents();
        },

        // =====================
        // WORKING HOURS
        // =====================

        addTimeSlot: function(e) {
            const dayNum = $(e.currentTarget).data('day');
            const container = $(e.currentTarget).closest('.time-slots-container').find('.time-slots-list');
            const slotIndex = container.find('.time-slot-row').length;
            
            const newSlot = `
                <div class="time-slot-row d-flex gap-2 align-items-center mb-2">
                    <input type="time" class="form-control form-control-sm" style="width: 120px;"
                           name="working_hours[${dayNum}][slots][${slotIndex}][start]" value="09:00">
                    <span class="text-muted">` + __('to', 'axiachat-ai') + `</span>
                    <input type="time" class="form-control form-control-sm" style="width: 120px;"
                           name="working_hours[${dayNum}][slots][${slotIndex}][end]" value="13:00">
                    <button type="button" class="btn btn-outline-danger btn-sm btn-remove-time-slot">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            
            container.append(newSlot);
        },

        removeTimeSlot: function(e) {
            const row = $(e.currentTarget).closest('.time-slot-row');
            const container = row.closest('.time-slots-list');
            
            if (container.find('.time-slot-row').length > 1) {
                row.remove();
            } else {
                this.showToast('warning', __('At least one time slot is required', 'axiachat-ai'));
            }
        },

        // =====================
        // SETTINGS FORM
        // =====================

        initSettingsForm: function() {
            const form = $('#appointments-settings-form');
            if (!form.length) return;
            
            form.on('submit', this.saveSettings.bind(this));
        },

        saveSettings: function(e) {
            e.preventDefault();
            const self = this;
            const form = $(e.currentTarget);
            const submitBtn = form.find('button[type="submit"]');
            
            submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>' + __('Saving...', 'axiachat-ai'));
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=aichat_appointments_save_settings',
                success: function(response) {
                    if (response.success) {
                        self.showToast('success', response.data || __('Settings saved', 'axiachat-ai'));
                    } else {
                        self.showToast('error', response.data || __('Error saving settings', 'axiachat-ai'));
                    }
                    submitBtn.prop('disabled', false).html('<i class="bi bi-check-lg me-2"></i>' + __('Save Settings', 'axiachat-ai'));
                },
                error: function() {
                    self.showToast('error', __('Connection error', 'axiachat-ai'));
                    submitBtn.prop('disabled', false).html('<i class="bi bi-check-lg me-2"></i>' + __('Save Settings', 'axiachat-ai'));
                }
            });
        },

        // =====================
        // UTILITIES
        // =====================

        showModal: function(content) {
            const self = this;
            let modal = $('#aichat-appointment-modal');
            
            // If modal exists, destroy old instance first
            if (modal.length) {
                const existingModal = bootstrap.Modal.getInstance(modal[0]);
                if (existingModal) {
                    existingModal.dispose();
                }
                modal.remove();
            }
            
            // Create fresh modal
            modal = $(`
                <div class="modal fade" id="aichat-appointment-modal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content"></div>
                    </div>
                </div>
            `);
            $('body').append(modal);
            
            if (content === 'loading') {
                modal.find('.modal-content').html(`
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">` + __('Loading...', 'axiachat-ai') + `</span>
                        </div>
                        <p class="mt-3 text-muted">` + __('Loading appointment details...', 'axiachat-ai') + `</p>
                    </div>
                `);
            } else {
                modal.find('.modal-content').html(content);
            }
            
            // Clean up backdrop and body class when modal is hidden
            modal.on('hidden.bs.modal', function() {
                $(this).remove();
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
            });
            
            const bsModal = new bootstrap.Modal(modal[0], {
                backdrop: true,
                keyboard: true
            });
            bsModal.show();
        },

        hideModal: function() {
            const modal = $('#aichat-appointment-modal');
            if (modal.length) {
                const bsModal = bootstrap.Modal.getInstance(modal[0]);
                if (bsModal) {
                    bsModal.hide();
                }
            }
            // Force cleanup
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('overflow', '');
        },

        showToast: function(type, message) {
            const toastClass = {
                success: 'bg-success text-white',
                error: 'bg-danger text-white',
                warning: 'bg-warning text-dark',
                info: 'bg-info text-white'
            }[type] || 'bg-secondary text-white';
            
            const icon = {
                success: 'bi-check-circle',
                error: 'bi-exclamation-circle',
                warning: 'bi-exclamation-triangle',
                info: 'bi-info-circle'
            }[type] || 'bi-info-circle';
            
            const toast = $(`
                <div class="toast ${toastClass} position-fixed" style="top: 20px; right: 20px; z-index: 99999;">
                    <div class="toast-body d-flex align-items-center">
                        <i class="bi ${icon} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            
            $('body').append(toast);
            const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
            bsToast.show();
            
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        },

        formatDate: function(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            return date.toLocaleDateString(undefined, { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        },

        formatDateTime: function(datetimeStr) {
            if (!datetimeStr) return '—';
            const date = new Date(datetimeStr);
            return date.toLocaleString();
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        Appointments.init();
    });

})(jQuery);
