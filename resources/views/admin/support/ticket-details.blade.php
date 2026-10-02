@extends('layouts/layoutMaster')

@section('title', 'Ticket Details - ' . $ticket->ticket_number)

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/ticket-details.js'])
@endsection

@section('page-style')
<style>
  .chat-container {
    max-height: 600px;
    overflow-y: auto;
  }

  .chat-message {
    margin-bottom: 1.5rem;
  }

  .chat-message.user-message {
    display: flex;
    justify-content: flex-end;
  }

  .chat-message.client-message {
    display: flex;
    justify-content: flex-start;
  }

  .message-bubble {
    max-width: 70%;
    padding: 1rem;
    border-radius: 0.5rem;
    position: relative;
  }

  .user-message .message-bubble {
    background-color: var(--bs-primary);
    color: white;
  }

  .client-message .message-bubble {
    background-color: var(--bs-light);
    color: var(--bs-body-color);
  }

  .attachment-item {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 0.375rem;
    margin-top: 0.5rem;
    margin-right: 0.5rem;
  }

  .client-message .attachment-item {
    background: rgba(0, 0, 0, 0.05);
  }
</style>
@endsection

@section('content')

<!-- Ticket Header -->
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center">
      <div class="col-md-8">
        <div class="d-flex align-items-center mb-3">
          <a href="{{ route('support-tickets') }}" class="btn btn-icon btn-sm btn-outline-secondary me-3">
            <i class="icon-base ri ri-arrow-left-line"></i>
          </a>
          <div>
            <h4 class="mb-1">{{ $ticket->ticket_number }}</h4>
            <p class="mb-0 text-muted">{{ $ticket->subject }}</p>
          </div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <div>
            <small class="text-muted">Client:</small>
            <strong class="ms-1">{{ $ticket->client->client_name ?? 'N/A' }}</strong>
          </div>
          <div>
            <small class="text-muted">Priority:</small>
            @php
              $priorityColors = [
                'low' => 'info',
                'medium' => 'primary',
                'high' => 'warning',
                'urgent' => 'danger'
              ];
              $color = $priorityColors[$ticket->priority] ?? 'secondary';
            @endphp
            <span class="badge bg-label-{{ $color }} ms-1">{{ ucfirst($ticket->priority) }}</span>
          </div>
          <div>
            <small class="text-muted">Status:</small>
            @php
              $statusColors = [
                'open' => 'success',
                'pending' => 'warning',
                'closed' => 'secondary'
              ];
              $statusColor = $statusColors[$ticket->status] ?? 'secondary';
            @endphp
            <span class="badge bg-label-{{ $statusColor }} ms-1">{{ ucfirst($ticket->status) }}</span>
          </div>
          <div>
            <small class="text-muted">Created:</small>
            <strong class="ms-1">{{ $ticket->created_at->format('d-m-Y h:i A') }}</strong>
          </div>
        </div>
      </div>
      <div class="col-md-4 text-md-end mt-3 mt-md-0">
        @if($ticket->status !== 'closed')
          <button type="button" class="btn btn-danger" id="closeTicketBtn">
            <i class="icon-base ri ri-close-circle-line me-1"></i> Close Ticket
          </button>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Ticket Conversation -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">Conversation</h5>
  </div>
  <div class="card-body">
    <!-- Chat Container -->
    <div class="chat-container" id="chatContainer">
      <!-- Initial Message -->
      <div class="chat-message client-message">
        <div class="message-bubble">
          <div class="d-flex align-items-center mb-2">
            <div class="avatar avatar-sm me-2">
              <div class="avatar-initial bg-label-primary rounded-circle">
                {{ substr($ticket->client->client_name ?? 'C', 0, 1) }}
              </div>
            </div>
            <div>
              <strong>{{ $ticket->client->client_name ?? 'Client' }}</strong>
              <small class="text-muted ms-2">{{ $ticket->created_at->format('d-m-Y h:i A') }}</small>
            </div>
          </div>
          <p class="mb-0">{{ $ticket->message }}</p>

          @if($ticket->attachments->where('reply_id', null)->count() > 0)
            <div class="mt-2">
              @foreach($ticket->attachments->where('reply_id', null) as $attachment)
                <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->file_path) }}" target="_blank" class="attachment-item text-decoration-none">
                  <i class="icon-base ri ri-attachment-2 me-1"></i>
                  <span>{{ $attachment->file_name }}</span>
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <!-- Replies -->
      @foreach($ticket->replies as $reply)
        @if($reply->user_id)
          <!-- Admin/User Reply -->
          <div class="chat-message user-message">
            <div class="message-bubble">
              <div class="d-flex align-items-center mb-2">
                <div class="avatar avatar-sm me-2">
                  <div class="avatar-initial bg-label-light rounded-circle">
                    {{ substr($reply->user->name ?? 'U', 0, 1) }}
                  </div>
                </div>
                <div>
                  <strong>{{ $reply->user->name ?? 'Support Team' }}</strong>
                  <small class="ms-2 opacity-75">{{ $reply->created_at->format('d-m-Y h:i A') }}</small>
                </div>
              </div>
              <p class="mb-0">{{ $reply->message }}</p>

              @if($reply->attachments->count() > 0)
                <div class="mt-2">
                  @foreach($reply->attachments as $attachment)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->file_path) }}" target="_blank" class="attachment-item text-decoration-none text-white">
                      <i class="icon-base ri ri-attachment-2 me-1"></i>
                      <span>{{ $attachment->file_name }}</span>
                    </a>
                  @endforeach
                </div>
              @endif
            </div>
          </div>
        @else
          <!-- Client Reply -->
          <div class="chat-message client-message">
            <div class="message-bubble">
              <div class="d-flex align-items-center mb-2">
                <div class="avatar avatar-sm me-2">
                  <div class="avatar-initial bg-label-primary rounded-circle">
                    {{ substr($reply->client->client_name ?? 'C', 0, 1) }}
                  </div>
                </div>
                <div>
                  <strong>{{ $reply->client->client_name ?? 'Client' }}</strong>
                  <small class="text-muted ms-2">{{ $reply->created_at->format('d-m-Y h:i A') }}</small>
                </div>
              </div>
              <p class="mb-0">{{ $reply->message }}</p>

              @if($reply->attachments->count() > 0)
                <div class="mt-2">
                  @foreach($reply->attachments as $attachment)
                    <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->file_path) }}" target="_blank" class="attachment-item text-decoration-none">
                      <i class="icon-base ri ri-attachment-2 me-1"></i>
                      <span>{{ $attachment->file_name }}</span>
                    </a>
                  @endforeach
                </div>
              @endif
            </div>
          </div>
        @endif
      @endforeach
    </div>

    <!-- Reply Form -->
    @if($ticket->status !== 'closed')
      <div class="border-top pt-3 mt-4">
        <form id="replyForm" enctype="multipart/form-data">
          <div class="card border shadow-none mb-0">
            <div class="card-body p-3">
              <div class="mb-2">
                <textarea id="replyMessage" name="message" class="form-control border-0 p-0 shadow-none" rows="3" placeholder="Type your reply here..." style="resize: none;" required></textarea>
              </div>

              <!-- Selected File Pills Preview -->
              <div id="filePreviewContainer" class="d-flex flex-wrap gap-2 mb-2 d-none"></div>

              <div class="d-flex justify-content-between align-items-center border-top pt-2">
                <div class="d-flex align-items-center gap-2">
                  <!-- File Attachment Icon right nearby message input -->
                  <label for="replyAttachments" class="btn btn-icon btn-outline-primary btn-sm rounded-circle mb-0 cursor-pointer" title="Attach files" style="cursor: pointer;">
                    <i class="icon-base ri ri-attachment-2 icon-20px"></i>
                  </label>
                  <input type="file" id="replyAttachments" name="attachments[]" class="d-none" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                  <span id="fileSelectedText" class="text-muted small">Attach file</span>
                </div>

                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4">
                  <i class="icon-base ri ri-send-plane-fill me-1"></i> Send Reply
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    @else
      <div class="alert alert-secondary mt-4 mb-0">
        <i class="icon-base ri ri-information-line me-2"></i>
        This ticket is closed. Change status to reopen and reply.
      </div>
    @endif
  </div>
</div>

<input type="hidden" id="ticketId" value="{{ $ticket->id }}">

<!-- Close Ticket Modal -->
<div class="modal fade" id="closeTicketModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Close Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to close this ticket?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmCloseTicket">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

@endsection
