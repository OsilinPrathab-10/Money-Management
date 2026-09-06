/**
 * Client Registration Form Validation
 * Handles input restrictions and validations for client registration forms
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // ============================================================
  // 1. AADHAR NUMBER VALIDATION (12 digits only)
  // ============================================================
  const aadhaarInput = document.getElementById('formValidationAadhar');
  if (aadhaarInput) {
    aadhaarInput.addEventListener('input', function (e) {
      // Remove non-numeric characters
      this.value = this.value.replace(/[^0-9]/g, '');
      // Limit to 12 digits
      if (this.value.length > 12) {
        this.value = this.value.substring(0, 12);
      }
      // Update feedback
      updateAadharFeedback(this);
    });

    aadhaarInput.addEventListener('blur', function (e) {
      if (this.value && this.value.length !== 12) {
        showFieldError(this, 'Aadhar Number must be exactly 12 digits');
      }
    });
  }

  function updateAadharFeedback(input) {
    const feedbackDiv = input.parentElement.querySelector('.invalid-feedback') || 
                        document.createElement('div');
    
    if (input.value && input.value.length !== 12) {
      feedbackDiv.textContent = `Aadhar Number must be exactly 12 digits (${input.value.length}/12)`;
      feedbackDiv.className = 'invalid-feedback';
      feedbackDiv.style.display = 'block';
      if (!input.parentElement.querySelector('.invalid-feedback')) {
        input.parentElement.appendChild(feedbackDiv);
      }
    } else if (input.value && input.value.length === 12) {
      feedbackDiv.style.display = 'none';
    }
  }

  // ============================================================
  // 2. BANK ACCOUNT NUMBER VALIDATION (Numbers only)
  // ============================================================
  const bankAccountInput = document.getElementById('formValidationBankAccount');
  if (bankAccountInput) {
    bankAccountInput.addEventListener('input', function (e) {
      // Remove non-numeric characters
      this.value = this.value.replace(/[^0-9]/g, '');
      // Limit to 18 digits (standard bank account length)
      if (this.value.length > 18) {
        this.value = this.value.substring(0, 18);
      }
    });

    bankAccountInput.addEventListener('blur', function (e) {
      if (this.value && this.value.length < 9) {
        showFieldError(this, 'Bank Account Number must be at least 9 digits');
      }
    });
  }

  // ============================================================
  // 3. MOBILE NUMBER VALIDATION (10 digits only)
  // ============================================================
  const mobileInputs = document.querySelectorAll('[name="formValidationMobile"], [name="formValidationNominee1Mobile"], [name="formValidationNominee2Mobile"]');
  
  mobileInputs.forEach(mobileInput => {
    if (mobileInput) {
      mobileInput.addEventListener('input', function (e) {
        // Extract only digits
        let digits = this.value.replace(/[^0-9]/g, '');
        
        // If user has country code, keep it formatted
        if (digits.length >= 10) {
          const lastTen = digits.slice(-10);
          this.value = '+91 ' + lastTen;
        } else {
          this.value = '+91 ' + digits;
        }
        
        updateMobileFeedback(this);
      });

      mobileInput.addEventListener('blur', function (e) {
        const digits = this.value.replace(/[^0-9]/g, '');
        if (this.value && digits.length !== 10) {
          showFieldError(this, 'Mobile Number must be exactly 10 digits');
        }
      });
    }
  });

  function updateMobileFeedback(input) {
    const digits = input.value.replace(/[^0-9]/g, '');
    const feedbackDiv = input.parentElement.querySelector('.invalid-feedback') || 
                        document.createElement('div');
    
    if (input.value && digits.length !== 10) {
      feedbackDiv.textContent = `Mobile Number must be exactly 10 digits (${digits.length}/10)`;
      feedbackDiv.className = 'invalid-feedback';
      feedbackDiv.style.display = 'block';
      if (!input.parentElement.querySelector('.invalid-feedback')) {
        input.parentElement.appendChild(feedbackDiv);
      }
    } else if (input.value && digits.length === 10) {
      feedbackDiv.style.display = 'none';
    }
  }

  // ============================================================
  // 4. EMPLOYMENT TYPE RADIO BUTTON HANDLER
  // ============================================================
  // Use the server-side field name `employment_type` so behavior matches the form
  const employmentRadios = document.querySelectorAll('input[name="employment_type"]');
  const salariedFields = document.getElementById('salariedFields');
  const businessFields = document.getElementById('businessFields');

  if (employmentRadios.length && salariedFields && businessFields) {
    employmentRadios.forEach(radio => {
      radio.addEventListener('change', function () {
        if (this.value === 'salaried') {
          salariedFields.style.display = 'block';
          businessFields.style.display = 'none';
          // Make salaried fields required
          setFieldsRequired(salariedFields, true);
          setFieldsRequired(businessFields, false);
        } else if (this.value === 'business') {
          salariedFields.style.display = 'none';
          businessFields.style.display = 'block';
          // Make business fields required
          setFieldsRequired(salariedFields, false);
          setFieldsRequired(businessFields, true);
        }
      });
    });

    // Trigger on page load
    const checkedRadio = document.querySelector('input[name="employment_type"]:checked');
    if (checkedRadio) {
      checkedRadio.dispatchEvent(new Event('change'));
    }
  }

  function setFieldsRequired(container, required) {
    const inputs = container.querySelectorAll('input[required], select[required]');
    inputs.forEach(input => {
      if (required) {
        input.setAttribute('required', '');
      } else {
        input.removeAttribute('required');
      }
    });
  }

  // ============================================================
  // 5. NUMERIC INPUT FIELDS VALIDATION
  // ============================================================
  const numericFields = document.querySelectorAll('[name="formValidationMonthlySalary"], [name="formValidationMonthlyIncome"]');
  
  numericFields.forEach(field => {
    if (field) {
      field.addEventListener('input', function (e) {
        this.value = this.value.replace(/[^0-9]/g, '');
      });
    }
  });

  // ============================================================
  // 6. HELPER FUNCTION TO SHOW FIELD ERROR
  // ============================================================
  function showFieldError(input, message) {
    input.classList.add('is-invalid');
    const feedbackDiv = input.parentElement.querySelector('.invalid-feedback') || 
                        document.createElement('div');
    feedbackDiv.textContent = message;
    feedbackDiv.className = 'invalid-feedback';
    feedbackDiv.style.display = 'block';
    if (!input.parentElement.querySelector('.invalid-feedback')) {
      input.parentElement.appendChild(feedbackDiv);
    }
  }

  // ============================================================
  // 7. FORM SUBMISSION VALIDATION
  // ============================================================
  const form = document.getElementById('formValidationExamples');
  if (form) {
    form.addEventListener('submit', function (e) {
      // Validate Aadhar
      if (aadhaarInput && aadhaarInput.value && aadhaarInput.value.length !== 12) {
        e.preventDefault();
        e.stopPropagation();
        showFieldError(aadhaarInput, 'Aadhar Number must be exactly 12 digits');
        return false;
      }

      // Validate Bank Account
      if (bankAccountInput && bankAccountInput.value && bankAccountInput.value.length < 9) {
        e.preventDefault();
        e.stopPropagation();
        showFieldError(bankAccountInput, 'Bank Account Number must be at least 9 digits');
        return false;
      }

      // Validate Mobile numbers
      mobileInputs.forEach(mobileInput => {
        if (mobileInput && mobileInput.value) {
          const digits = mobileInput.value.replace(/[^0-9]/g, '');
          if (digits.length !== 10) {
            e.preventDefault();
            e.stopPropagation();
            showFieldError(mobileInput, 'Mobile Number must be exactly 10 digits');
            return false;
          }
        }
      });
    });
  }
});
