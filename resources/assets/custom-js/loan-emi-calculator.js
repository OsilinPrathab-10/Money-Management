/**
 * Loan EMI Calculator - Enhanced Version
 * Handles loan amount, tenure, and EMI calculations with improved manual entry UX
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const loanProductSelect = document.getElementById('loan_product');
  const loanAmountInput = document.getElementById('loan_amount_input');
  const loanAmountSlider = document.getElementById('loan_amount_slider');
  const tenureInput = document.getElementById('tenure_input');
  const tenureSlider = document.getElementById('tenure_slider');
  const displayTenure = document.getElementById('display_tenure');
  const minAmountLabel = document.getElementById('min_amount_label');
  const maxAmountLabel = document.getElementById('max_amount_label');
  const minTenureLabel = document.getElementById('min_tenure_label');
  const maxTenureLabel = document.getElementById('max_tenure_label');
  const previewEmi = document.getElementById('preview_emi');
  const previewInterest = document.getElementById('preview_interest');
  const previewTotal = document.getElementById('preview_total');
  const amountRangeInfo = document.getElementById('amount_range_info');

  // Store current product data
  let currentProduct = {
    minAmount: 0,
    maxAmount: 1000000,
    minTenure: 1,
    maxTenure: 60,
    ratePerAnnum: 12
  };

  let productSelected = false;
  let debounceTimer;

  /**
   * Debounce helper
   */
  function debounce(func, timeout = 300) {
    return (...args) => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => { func.apply(this, args); }, timeout);
    };
  }

  const debouncedCalculate = debounce(calculateLoanSummary, 50);

  /**
   * Format currency for display
   */
  function formatCurrency(value) {
    return '₹' + Number(value).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  /**
   * Calculate EMI using FLAT INTEREST formula
   */
  function calculateEMI(principal, annualRate, intervals) {
    if (!principal || !intervals || annualRate < 0) {
      return 0;
    }

    // Flat Interest: Interest = Principal * (Rate/100)
    const totalInterest = principal * (annualRate / 100);
    const totalPayable = principal + totalInterest;
    const emi = totalPayable / intervals;

    return Math.round(emi * 100) / 100; // Round to 2 decimal places
  }

  /**
   * Calculate total interest and payable amount
   */
  function calculateLoanSummary() {
    if (!loanAmountInput || !tenureSlider) return;

    const principal = Math.floor(Number(loanAmountInput.value) || 0);
    const intervals = Number(tenureSlider.value) || 1;
    const rate = currentProduct.ratePerAnnum || 0;
    const frequency = document.getElementById('repayment_frequency')?.value || 'monthly';

    // Update labels based on frequency
    const frequencyLabel = frequency === 'weekly' ? 'Weekly' : (frequency === 'daily' ? 'Daily' : 'Monthly');
    const unitLabel = frequency === 'weekly' ? 'Week' : (frequency === 'daily' ? 'Day' : 'Month');
    
    document.querySelectorAll('.preview-label').forEach(el => {
        if (el.textContent.includes('EMI')) {
            el.textContent = `${frequencyLabel} EMI`;
        }
    });

    if (displayTenure) {
        displayTenure.textContent = `${intervals} ${unitLabel}${intervals > 1 ? 's' : ''}`;
    }

    // Don't update if principal is 0 yet
    if (principal === 0) {
      if (previewEmi) previewEmi.textContent = formatCurrency(0);
      if (previewInterest) previewInterest.textContent = formatCurrency(0);
      if (previewTotal) previewTotal.textContent = formatCurrency(0);
      return;
    }

    // If product is selected, validate range (visual feedback only)
    if (productSelected) {
      if (principal < currentProduct.minAmount || principal > currentProduct.maxAmount) {
        if (previewEmi) previewEmi.textContent = formatCurrency(0);
        if (previewInterest) previewInterest.textContent = formatCurrency(0);
        if (previewTotal) previewTotal.textContent = formatCurrency(0);
        return;
      }
    }

    // Calculate Flat Interest Summary
    const totalInterest = principal * (rate / 100);
    const totalPayable = principal + totalInterest;
    const emi = totalPayable / intervals;

    if (previewEmi) previewEmi.textContent = formatCurrency(emi);
    if (previewInterest) previewInterest.textContent = formatCurrency(totalInterest);
    if (previewTotal) previewTotal.textContent = formatCurrency(totalPayable);
  }

  /**
   * Update amount range info display
   */
  function updateAmountRangeInfo() {
    if (amountRangeInfo) {
      if (productSelected) {
        amountRangeInfo.innerHTML = `<i class="fas fa-check-circle text-success"></i> ${formatCurrency(currentProduct.minAmount)} - ${formatCurrency(currentProduct.maxAmount)}`;
        amountRangeInfo.className = 'text-success fw-semibold';
      } else {
        amountRangeInfo.textContent = 'Select a product first';
        amountRangeInfo.className = 'text-info';
      }
    }
  }

  /**
   * Initialize default state
   */
  function initializeDefaults() {
    // Set initial default values for amount
    if (loanAmountSlider) {
      loanAmountSlider.min = 0;
      loanAmountSlider.max = 1000000;
      loanAmountSlider.step = 10000;
      loanAmountSlider.value = 100000;
    }

    if (loanAmountInput) {
      loanAmountInput.value = 100000;
      loanAmountInput.min = 0;
      loanAmountInput.max = 1000000;
      loanAmountInput.placeholder = 'Enter or adjust amount below | Select product for specific range';
    }

    // Set initial default values for tenure
    if (tenureSlider) {
      tenureSlider.min = 1;
      tenureSlider.max = 60;
      tenureSlider.step = 1;
      tenureSlider.value = 12;
    }

    if (tenureInput) {
      tenureInput.value = 12;
      tenureInput.min = 1;
      tenureInput.max = 60;
    }

    if (displayTenure) {
      displayTenure.textContent = '12 Months';
    }

    // Update min/max labels
    updateAmountLabels();
    updateTenureLabels();
    updateAmountRangeInfo();
    calculateLoanSummary();
  }

  // Initialize on page load
  initializeDefaults();

  /**
   * Handle loan product selection
   */
  if (loanProductSelect) {
    loanProductSelect.addEventListener('change', function () {
      const selectedOption = this.options[this.selectedIndex];

      if (!selectedOption.value) {
        productSelected = false;
        currentProduct = {
          minAmount: 0,
          maxAmount: 1000000,
          minTenure: 1,
          maxTenure: 60,
          ratePerAnnum: 12
        };
        
        if (loanAmountInput) {
          loanAmountInput.placeholder = 'Enter or adjust amount below | Select product for specific range';
          loanAmountInput.min = 0;
          loanAmountInput.max = 1000000;
        }

        if (tenureInput) {
            tenureInput.min = 1;
            tenureInput.max = 60;
        }
        
        // Keep current values, don't clear
        initializeDefaults();
        return;
      }

      productSelected = true;

      // Get product data from data attributes
      currentProduct.minAmount = Number(selectedOption.dataset.minAmount) || 0;
      currentProduct.maxAmount = Number(selectedOption.dataset.maxAmount) || 1000000;
      currentProduct.minTenure = Number(selectedOption.dataset.minTenure) || 1;
      currentProduct.maxTenure = Number(selectedOption.dataset.maxTenure) || 60;
      currentProduct.ratePerAnnum = Number(selectedOption.dataset.rate) || 12;

      // Update inputs with product constraints
      if (loanAmountInput) {
          loanAmountInput.min = currentProduct.minAmount;
          loanAmountInput.max = currentProduct.maxAmount;
      }
      if (tenureInput) {
          tenureInput.min = currentProduct.minTenure;
          tenureInput.max = currentProduct.maxTenure;
      }

      // Update sliders with product constraints
      if (loanAmountSlider) {
        loanAmountSlider.min = currentProduct.minAmount;
        loanAmountSlider.max = currentProduct.maxAmount;
        loanAmountSlider.step = Math.max(1000, Math.floor(currentProduct.maxAmount / 100));
        
        // Adjust current value if outside new range
        let currentVal = Number(loanAmountSlider.value);
        if (currentVal < currentProduct.minAmount) {
          currentVal = currentProduct.minAmount;
        }
        if (currentVal > currentProduct.maxAmount) {
          currentVal = currentProduct.maxAmount;
        }
        loanAmountSlider.value = currentVal;
        if (loanAmountInput) loanAmountInput.value = currentVal;
      }

      if (tenureSlider) {
        tenureSlider.min = currentProduct.minTenure;
        tenureSlider.max = currentProduct.maxTenure;
        
        // Adjust tenure if outside new range
        let currentTenure = Number(tenureSlider.value);
        if (currentTenure < currentProduct.minTenure) {
          currentTenure = currentProduct.minTenure;
        }
        if (currentTenure > currentProduct.maxTenure) {
          currentTenure = currentProduct.maxTenure;
        }
        tenureSlider.value = currentTenure;
        if (tenureInput) tenureInput.value = currentTenure;
      }

      // Update labels and info
      updateAmountLabels();
      updateTenureLabels();
      updateAmountRangeInfo();
      
      if (loanAmountInput) {
        loanAmountInput.placeholder = 'Adjust with slider or enter amount';
      }

      calculateLoanSummary();
    });
  }

  /**
   * Handle repayment frequency changes
   */
  const repaymentFrequencySelect = document.getElementById('repayment_frequency');
  if (repaymentFrequencySelect) {
    repaymentFrequencySelect.addEventListener('change', function() {
        // Update tenure labels and recalculate
        updateTenureLabels();
        calculateLoanSummary();
    });
  }

  /**
   * Handle loan amount input changes
   */
  if (loanAmountInput) {
    loanAmountInput.addEventListener('input', function () {
      let value = Number(this.value.replace(/[^0-9]/g, '')) || 0;

      // Update slider to match input (visual feedback)
      if (loanAmountSlider) {
        loanAmountSlider.value = value;
      }

      calculateLoanSummary();
    });

    loanAmountInput.addEventListener('blur', function () {
      // Reformat on blur - clean up the value and clamp if product selected
      let value = Number(this.value.replace(/[^0-9]/g, '')) || 0;
      
      if (productSelected) {
        if (value < currentProduct.minAmount) {
          value = currentProduct.minAmount;
        } else if (value > currentProduct.maxAmount) {
          value = currentProduct.maxAmount;
        }
      }
      
      this.value = value > 0 ? value : '';
      if (loanAmountSlider) loanAmountSlider.value = value;
      calculateLoanSummary();
    });

    loanAmountInput.addEventListener('focus', function () {
      // Clean up on focus for easier editing
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  }

  /**
   * Handle loan amount slider changes
   */
  if (loanAmountSlider) {
    loanAmountSlider.addEventListener('input', function () {
      const value = Number(this.value);
      if (loanAmountInput) {
        loanAmountInput.value = value;
      }
      calculateLoanSummary();
    });
  }

  /**
   * Handle tenure input changes
   */
  if (tenureInput) {
    tenureInput.addEventListener('input', function () {
      let value = Number(this.value.replace(/[^0-9]/g, '')) || 1;

      // Update slider to match input
      if (tenureSlider) {
        tenureSlider.value = value;
      }

      if (displayTenure) {
        displayTenure.textContent = value + ' Month' + (value > 1 ? 's' : '');
      }

      calculateLoanSummary();
    });

    tenureInput.addEventListener('blur', function () {
      let value = Number(this.value.replace(/[^0-9]/g, '')) || 1;
      
      if (productSelected) {
        if (value < currentProduct.minTenure) {
          value = currentProduct.minTenure;
        } else if (value > currentProduct.maxTenure) {
          value = currentProduct.maxTenure;
        }
      }
      
      this.value = value;
      if (tenureSlider) tenureSlider.value = value;
      if (displayTenure) displayTenure.textContent = value + ' Month' + (value > 1 ? 's' : '');
      calculateLoanSummary();
    });
  }

  /**
   * Handle tenure slider changes
   */
  if (tenureSlider) {
    tenureSlider.addEventListener('input', function () {
      const months = Number(this.value);
      if (tenureInput) {
          tenureInput.value = months;
      }
      if (displayTenure) {
        displayTenure.textContent = months + ' Month' + (months > 1 ? 's' : '');
      }
      calculateLoanSummary();
    });
    
    // Initialize tenure display
    if (displayTenure) {
      const initialMonths = Number(tenureSlider.value) || 12;
      displayTenure.textContent = initialMonths + ' Month' + (initialMonths > 1 ? 's' : '');
    }
  }

  /**
   * Update amount labels based on current product
   */
  function updateAmountLabels() {
    if (minAmountLabel) {
      minAmountLabel.textContent = 'Min: ' + formatCurrency(currentProduct.minAmount);
    }
    if (maxAmountLabel) {
      maxAmountLabel.textContent = 'Max: ' + formatCurrency(currentProduct.maxAmount);
    }
  }

  /**
   * Update tenure labels based on current product
   */
  function updateTenureLabels() {
    if (minTenureLabel) {
      minTenureLabel.textContent = currentProduct.minTenure + ' m';
    }
    if (maxTenureLabel) {
      maxTenureLabel.textContent = currentProduct.maxTenure + ' m';
    }

    if (displayTenure) {
      const months = Number(tenureSlider.value);
      displayTenure.textContent = months + ' Month' + (months > 1 ? 's' : '');
    }
  }

  // Note: Form submission is handled by app-kyc-verification.js
  // This script focuses only on EMI calculations and product selection UX

});
