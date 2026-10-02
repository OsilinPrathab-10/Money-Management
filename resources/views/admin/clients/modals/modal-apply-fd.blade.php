<div class="modal fade" id="modalApplyFd" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body p-0">
        <div class="p-6 text-center bg-primary position-relative overflow-hidden" style="border-radius: 0.5rem 0.5rem 0 0;">
          <div class="position-absolute w-100 h-100 top-0 start-0" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); z-index: 1;"></div>
          <div class="position-relative" style="z-index: 2;">
            <h3 class="text-white mb-2">Apply for Fixed Deposit</h3>
            <p class="text-white opacity-75 mb-0">Select an FD scheme and configure deposit terms</p>
          </div>
        </div>

        <div class="p-6">
          @include('admin.clients.modals.partials.apply-fd-form', [
            'formId' => 'formApplyFd',
            'preselectClient' => $client ?? null,
            'verifiedClients' => $verifiedClients ?? collect(),
            'fdSchemes' => $fdSchemes ?? collect(),
            'payoutOptions' => $payoutOptions ?? \App\Models\FixedDepositScheme::payoutOptions(),
          ])
        </div>
      </div>
    </div>
  </div>
</div>
