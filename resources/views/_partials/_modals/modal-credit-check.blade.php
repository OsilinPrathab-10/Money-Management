<!-- Credit Check Modal -->
<div class="modal fade" id="modalCreditCheck" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-simple">
        <div class="modal-content p-2 p-md-5">
            <div class="modal-body p-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                <!-- Step 1: Basic Details -->
                <div id="step-1" class="credit-step">
                    <div class="text-center mb-6">
                        <h4 class="mb-2">Check Your Free Credit Score</h4>
                        <p>Fill in your details to get your credit report instantly via Aadhaar OTP.</p>
                    </div>
                    <form id="formCreditCheckInit" class="row g-3">
                        @csrf
                        <div class="col-md-12">
                            <div class="form-floating form-floating-outline">
                                <input type="text" name="applicant_name" id="cc_name" class="form-control" placeholder="Full Name (as per PAN)" required />
                                <label for="cc_name">Full Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="email" name="email" id="cc_email" class="form-control" placeholder="name@example.com" required />
                                <label for="cc_email">Email Address</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" name="phone" id="cc_phone" class="form-control" placeholder="10 Digit Mobile" required />
                                <label for="cc_phone">Mobile Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" name="pan_number" id="cc_pan" class="form-control text-uppercase" placeholder="ABCDE1234F" maxlength="10" required />
                                <label for="cc_pan">PAN Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <input type="text" name="aadhaar_number" id="cc_aadhaar" class="form-control" placeholder="12 Digit Aadhaar" maxlength="12" required />
                                <label for="cc_aadhaar">Aadhaar Number</label>
                            </div>
                        </div>
                        <div class="col-12 text-center mt-4">
                            <button type="submit" class="btn btn-primary me-sm-3 me-1" id="btnInitCheck">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                                Send Aadhaar OTP
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 2: OTP Verification -->
                <div id="step-2" class="credit-step d-none">
                    <div class="text-center mb-6">
                        <h4 class="mb-2">Aadhaar OTP Verification</h4>
                        <p>Enter the 6-digit OTP sent to your Aadhaar-linked mobile number.</p>
                    </div>
                    <form id="formCreditCheckVerify" class="row g-3">
                        @csrf
                        <input type="hidden" name="reference_id" id="cc_reference_id" />
                        <!-- Shadow hidden inputs from step 1 for re-verification if needed -->
                        <div class="col-12">
                            <div class="form-floating form-floating-outline">
                                <input type="text" name="otp" id="cc_otp" class="form-control text-center fs-2" placeholder="000000" maxlength="6" required />
                                <label for="cc_otp">OTP Code</label>
                            </div>
                        </div>
                        <div class="col-12 text-center mt-4">
                            <button type="submit" class="btn btn-primary me-sm-3 me-1" id="btnVerifyCheck">
                                <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                                Verify & Get Score
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btnBackTo1">Back</button>
                        </div>
                    </form>
                </div>

                <!-- Step 3: Result -->
                <div id="step-3" class="credit-step d-none">
                    <div class="text-center mb-6">
                        <i class="ri-checkbox-circle-line display-1 text-success mb-4"></i>
                        <h4 class="mb-2">Your Credit Score is Ready!</h4>
                    </div>
                    
                    <div class="row text-center mb-6">
                        <div class="col-12">
                            <div class="d-flex justify-content-center align-items-center flex-column">
                                <div class="h1 display-3 fw-bold decoration-heading text-primary mb-0" id="cc_score_display">750</div>
                                <div class="badge bg-label-success fs-5 px-4 mb-3" id="cc_rating_display">Good</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="ri-information-line me-2"></i>
                        A detailed report has been generated for you. You can download it as a PDF or Print it.
                    </div>

                    <div class="col-12 text-center mt-4 pb-4">
                        <a href="#" id="btnDownloadPDF" class="btn btn-success btn-lg me-sm-3 me-1">
                            <i class="ri-file-download-line me-1"></i> Download PDF
                        </a>
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('modalCreditCheck');
    const formInit = document.getElementById('formCreditCheckInit');
    const formVerify = document.getElementById('formCreditCheckVerify');
    const btnInit = document.getElementById('btnInitCheck');
    const btnVerify = document.getElementById('btnVerifyCheck');
    
    // Step transitions
    function showStep(step) {
        document.querySelectorAll('.credit-step').forEach(s => s.classList.add('d-none'));
        document.getElementById('step-' + step).classList.remove('d-none');
    }

    // Step 1: Initiate
    formInit.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(formInit);
        
        btnInit.disabled = true;
        btnInit.querySelector('.spinner-border').classList.remove('d-none');

        fetch('{{ route("public.credit-check.send-otp") }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            btnInit.disabled = false;
            btnInit.querySelector('.spinner-border').classList.add('d-none');

            if (data.success) {
                document.getElementById('cc_reference_id').value = data.reference_id;
                showStep(2);
            } else {
                alert(data.message || 'Error occurred');
            }
        })
        .catch(err => {
            btnInit.disabled = false;
            btnInit.querySelector('.spinner-border').classList.add('d-none');
            alert('Something went wrong. Please try again.');
        });
    });

    // Step 2: Verify
    formVerify.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(formVerify);
        // Append data from step 1
        new FormData(formInit).forEach((value, key) => {
            if (!formData.has(key)) formData.append(key, value);
        });

        btnVerify.disabled = true;
        btnVerify.querySelector('.spinner-border').classList.remove('d-none');

        fetch('{{ route("public.credit-check.verify") }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            btnVerify.disabled = false;
            btnVerify.querySelector('.spinner-border').classList.add('d-none');

            if (data.success) {
                document.getElementById('cc_score_display').innerText = data.score;
                document.getElementById('cc_rating_display').innerText = data.rating;
                
                // Update download link
                const downloadUrl = '{{ route("public.credit-check.report", ":id") }}'.replace(':id', data.history_id);
                document.getElementById('btnDownloadPDF').href = downloadUrl;
                
                showStep(3);
            } else {
                alert(data.message || 'Verification failed');
            }
        })
        .catch(err => {
            btnVerify.disabled = false;
            btnVerify.querySelector('.spinner-border').classList.add('d-none');
            alert('Verification failed. Please try again.');
        });
    });

    document.getElementById('btnBackTo1').addEventListener('click', () => showStep(1));
});
</script>
