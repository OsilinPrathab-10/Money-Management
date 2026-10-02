<div class="modal fade" id="modalApplyChit" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body p-0">
        <!-- Header with Glassmorphism -->
        <div class="p-6 text-center bg-primary position-relative overflow-hidden" style="border-radius: 0.5rem 0.5rem 0 0;">
          <div class="position-absolute w-100 h-100 top-0 start-0" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); z-index: 1;"></div>
          <div class="position-relative" style="z-index: 2;">
            <h3 class="text-white mb-1">Apply for Chit</h3>
            <p class="text-white opacity-75 mb-0">
              @if(isset($client) && $client)
                Submit chit application for {{ $client->client_name }}
              @else
                Select a KYC verified client and a chit group
              @endif
            </p>
          </div>
        </div>
        <div class="p-6">
          <form id="formApplyChit" class="row g-4">
            @csrf

            @if(isset($client) && $client)
              <input type="hidden" name="client_id" value="{{ $client->id }}">
              <div class="col-12">
                <div class="alert alert-primary mb-0 d-flex align-items-center gap-3">
                  <div class="avatar avatar-sm bg-white rounded-circle text-primary d-flex align-items-center justify-content-center">
                    <i class="ri-user-3-line"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 text-primary fw-bold">{{ $client->client_name }}</h6>
                    <small class="text-muted">Phone: {{ $client->client_phone }} | Customer ID: {{ $client->displayCustomerId() }}</small>
                  </div>
                </div>
              </div>
            @else
              <div class="col-12">
                <label class="form-label fw-semibold" for="chit_client_id">Select Verified Client <span class="text-danger">*</span></label>
                <select id="chit_client_id" name="client_id" class="form-select select2" required data-placeholder="Search client by name or phone">
                  <option></option>
                  @foreach(($verifiedClients ?? []) as $vClient)
                    <option value="{{ $vClient->id }}">{{ $vClient->client_name }} ({{ $vClient->client_phone }})</option>
                  @endforeach
                </select>
              </div>
            @endif

            <div class="col-12">
              <label class="form-label fw-semibold" for="chit_group_id">Select Chit Group <span class="text-danger">*</span></label>
              <select id="chit_group_id" name="group_id" class="form-select select2" required data-placeholder="Select available group">
                <option></option>
                @foreach(($availableGroups ?? []) as $group)
                  @php
                    $enrolled = $group->valid_members_count ?? $group->members_count ?? 0;
                    $startLabel = optional($group->start_date)->format('d-m-Y') ?: '—';
                    $endLabel = optional($group->end_date)->format('d-m-Y') ?: '—';
                  @endphp
                  <option value="{{ $group->id }}"
                          data-group-code="{{ $group->group_code }}"
                          data-scheme-name="{{ $group->scheme->name ?? '—' }}"
                          data-chit-value="{{ $group->chit_value }}"
                          data-installment="{{ $group->installment_amount }}"
                          data-settlement-amount="{{ $group->settlement_amount ?? 0 }}"
                          data-members="{{ $enrolled }}/{{ $group->total_members }}"
                          data-vacancy="{{ max(0, (int) $group->total_members - (int) $enrolled) }}"
                          data-status="{{ $group->status }}"
                          data-start-date="{{ optional($group->start_date)->format('Y-m-d') }}"
                          data-start-label="{{ $startLabel }}"
                          data-end-label="{{ $endLabel }}"
                          data-current-month="{{ $group->current_month ?? 0 }}"
                          data-total-months="{{ $group->total_months }}"
                          data-frequency="{{ $group->installment_frequency ?? optional($group->scheme)->installment_frequency ?? 'monthly' }}">
                    {{ $group->group_code }} — {{ $group->scheme->name ?? '—' }} (₹{{ number_format($group->chit_value, 0) }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-12" id="groupInfoPanel">
              <div class="alert alert-info border mb-0 py-3 px-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                  <i class="ri-information-line ri-20px"></i>
                  <strong id="infoGroupTitle">Select a chit group to see details</strong>
                  <span class="badge bg-label-secondary ms-auto text-capitalize" id="infoStatus">—</span>
                </div>
                <div class="row g-3 text-center">
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Chit Value</small>
                    <strong class="text-primary" id="infoChitValue">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Installment</small>
                    <strong id="infoInstallment">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Settlement Amount</small>
                    <strong class="text-success" id="infoSettlementAmount">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Members / Vacancy</small>
                    <strong id="infoMembers">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Duration</small>
                    <strong id="infoDuration">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Start Date</small>
                    <strong id="infoStartDate">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">End Date</small>
                    <strong id="infoEndDate">—</strong>
                  </div>
                  <div class="col-6 col-md-3">
                    <small class="text-muted d-block">Collection</small>
                    <strong class="text-capitalize" id="infoFrequency">—</strong>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold" for="chit_collection_frequency">Collection Frequency <span class="text-danger">*</span></label>
              <select id="chit_collection_frequency" name="collection_frequency" class="form-select select2" required
                      data-placeholder="Select collection frequency">
                <option value="monthly" selected>Monthly (Default)</option>
                <option value="weekly">Weekly</option>
                <option value="daily">Daily</option>
              </select>
              <div class="form-text small text-muted">Choose Monthly, Weekly, or Daily. Weekly = ÷4, Daily = ÷30 of monthly installment.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Referred By</label>
              <select id="chit_referred_by" name="referred_by" class="form-select select2" data-placeholder="Select referrer (optional)">
                <option></option>
                <optgroup label="Agents">
                  @foreach(($agents ?? []) as $agent)
                    <option value="agent_{{ $agent->id }}">{{ $agent->agent_name }} (Agent)</option>
                  @endforeach
                </optgroup>
                <optgroup label="Clients">
                  @foreach(($verifiedClients ?? []) as $c)
                    <option value="client_{{ $c->id }}">{{ $c->client_name }} (Client)</option>
                  @endforeach
                </optgroup>
              </select>
            </div>

            <div class="col-12" id="collectionSplitPreview" style="display:none;">
              <div class="alert alert-info border-0 mb-0 py-2 px-3 small">
                <strong>Collection split:</strong>
                Monthly <span id="splitMonthlyAmount">—</span>
                → <span id="splitFrequencyLabel">Weekly</span> collection
                <strong id="splitPeriodAmount">—</strong>
                (<span id="splitPartsHint"></span>)
              </div>
            </div>

            <div class="col-12 text-center mt-4">
              <button type="submit" class="btn btn-primary me-2" id="btnSubmitChitApp">
                <i class="ri-send-plane-line me-1"></i> Submit Application
              </button>
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
