/**
 * EMI Calculator
 */

'use strict';

(function () {
  let emiChart;
  let scheduleData = [];
  let latestScheduleRequest = 0;
  const baseUrl = window.baseUrl || document.documentElement.getAttribute('data-base-url') + '/' || window.location.origin + '/';

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('loanAmount')) return;
    initializeSliders();
    initializeChart();
    calculateEMI();
  });

  // Initialize sliders
  function initializeSliders() {
    const loanAmountSlider = document.getElementById('loanAmount');
    const interestRateSlider = document.getElementById('interestRate');
    const tenureSlider = document.getElementById('tenure');

    if (loanAmountSlider) {
      loanAmountSlider.addEventListener('input', function () {
        updateSliderDisplay('loanAmount', this.value);
        updateSliderBackground(this);
        calculateEMIInstant();
      }); 
      updateSliderBackground(loanAmountSlider);
    }

    if (interestRateSlider) {
      interestRateSlider.addEventListener('input', function () {
        updateSliderDisplay('interestRate', this.value);
        updateSliderBackground(this);
        calculateEMIInstant();
      });
      updateSliderBackground(interestRateSlider);
    }

    if (tenureSlider) {
      tenureSlider.addEventListener('input', function () {
        updateSliderDisplay('tenure', this.value);
        updateSliderBackground(this);
        calculateEMIInstant();
      });
      updateSliderBackground(tenureSlider);
    }

    const freqSelect = document.getElementById('repaymentFrequency');
    if (freqSelect) {
      freqSelect.addEventListener('change', function () {
        const frequency = this.value;
        const tenureSlider = document.getElementById('tenure');
        if (frequency === 'daily') {
          tenureSlider.max = 365;
          tenureSlider.value = Math.min(tenureSlider.value, 365);
        } else if (frequency === 'weekly') {
          tenureSlider.max = 104;
          tenureSlider.value = Math.min(tenureSlider.value, 104);
        } else {
          tenureSlider.max = 60;
          tenureSlider.value = Math.min(tenureSlider.value, 60);
        }
        updateSliderDisplay('tenure', tenureSlider.value);
        updateSliderBackground(tenureSlider);
        calculateEMIInstant();
      });
    }
  }

  // Update slider display value
  function updateSliderDisplay(type, value) {
    const displayElement = document.getElementById(`${type}Display`);
    if (!displayElement) return;

    switch (type) {
      case 'loanAmount':
        displayElement.textContent = `₹ ${formatNumber(value)}`;
        break;
      case 'interestRate':
        displayElement.textContent = `${value} %`;
        break;
      case 'tenure':
        const frequency = document.getElementById('repaymentFrequency').value;
        const unit = frequency === 'weekly' ? 'Weeks' : (frequency === 'daily' ? 'Days' : 'Months');
        displayElement.textContent = `${value} ${unit}`;
        break;
    }
  }

  // Update slider background gradient
  function updateSliderBackground(slider) {
    const value = ((slider.value - slider.min) / (slider.max - slider.min)) * 100;
    slider.style.background = `linear-gradient(to right, #00a86b ${value}%, #e0e0e0 ${value}%)`;
  }

  // Format number with commas
  function formatNumber(num) {
    return parseFloat(num).toLocaleString('en-IN');
  }

  // Format currency
  function formatCurrency(num) {
    return `₹${formatNumber(Math.round(num))}`;
  }

  // Initialize ApexCharts donut chart
  function initializeChart() {
    const chartElement = document.querySelector('#emiChart');
    if (!chartElement) return;

    const chartOptions = {
      series: [2410000, 352028],
      chart: {
        height: 350,
        type: 'donut'
      },
      labels: ['Principal Amount', 'Interest Amount'],
      colors: ['#44d7b6', '#5b6ef8'],
      plotOptions: {
        pie: {
          donut: {
            size: '70%',
            labels: {
              show: false
            }
          }
        }
      },
      fill: {
        opacity: 1
      },
      states: {
        hover: {
          filter: {
            type: 'none'
          }
        },
        active: {
          filter: {
            type: 'none'
          }
        }
      },
      dataLabels: {
        enabled: false
      },
      legend: {
        show: false
      },
      stroke: {
        width: 0
      },
      tooltip: {
        marker: {
          show: true,
          fillColors: ['#44d7b6', '#5b6ef8']
        },
        y: {
          formatter: function (val) {
            return formatCurrency(val);
          }
        }
      }
    };

    emiChart = new ApexCharts(chartElement, chartOptions);
    emiChart.render();
  }

  // Calculate EMI instantly (client-side)
  function calculateEMIInstant() {
    const loanAmount = parseFloat(document.getElementById('loanAmount').value);
    const interestRate = parseFloat(document.getElementById('interestRate').value);
    const tenure = parseInt(document.getElementById('tenure').value);
    const intervals = tenure;
    const frequency = document.getElementById('repaymentFrequency').value;

    // Update result label based on frequency
    const freqLabel = frequency.charAt(0).toUpperCase() + frequency.slice(1);
    document.querySelector('.result-label').textContent = `${freqLabel} EMI`;

    // Flat Interest: Total Interest = Principal * (Rate / 100)
    const totalInterest = loanAmount * (interestRate / 100);
    const totalPayment = loanAmount + totalInterest;
    const emi = totalPayment / intervals;
    
    // Update display instantly
    const data = {
      emi: emi.toFixed(2),
      total_interest: totalInterest.toFixed(2),
      total_payment: totalPayment.toFixed(2)
    };

    updateResults(data);
    updateChart(loanAmount, totalInterest);

    // Fetch detailed schedule from backend (debounced)
    clearTimeout(window.emiScheduleTimeout);
    window.emiScheduleTimeout = setTimeout(() => {
      fetchDetailedSchedule(loanAmount, interestRate, intervals, frequency);
    }, 500);
  }

  // Fetch detailed schedule from backend
  function fetchDetailedSchedule(loanAmount, interestRate, intervals, frequency) {
    const requestId = ++latestScheduleRequest;
    fetch(baseUrl + 'emi/calculate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        principal: loanAmount,
        annual_rate: interestRate,
        term_months: intervals,
        frequency: frequency,
        start_date: new Date().toISOString().split('T')[0]
      })
    })
      .then(response => response.json())
      .then(data => {
        if (requestId !== latestScheduleRequest) return;
        if (data.success) {
          scheduleData = Array.isArray(data.data.schedule) ? data.data.schedule : [];
          updateAmortizationTable(scheduleData);
        }
      })
      .catch(error => {
        console.error('Error fetching schedule:', error);
      });
  }

  // Calculate EMI using backend API (kept for initial load)
  function calculateEMI() {
    const loanAmount = parseFloat(document.getElementById('loanAmount').value);
    const interestRate = parseFloat(document.getElementById('interestRate').value);
    const tenure = parseInt(document.getElementById('tenure').value);
    const termMonths = tenure * 12;

    const requestId = ++latestScheduleRequest;
    // Make AJAX call to backend
    fetch(baseUrl + 'emi/calculate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        principal: loanAmount,
        annual_rate: interestRate,
        term_months: termMonths,
        start_date: new Date().toISOString().split('T')[0]
      })
    })
      .then(response => response.json())
      .then(data => {
        if (requestId !== latestScheduleRequest) return;
        if (data.success) {
          updateResults(data.data);
          updateChart(loanAmount, data.data.total_interest);
          scheduleData = Array.isArray(data.data.schedule) ? data.data.schedule : [];
          updateAmortizationTable(scheduleData);
        }
      })
      .catch(error => {
        console.error('Error calculating EMI:', error);
      });
  }

  // Update result displays
  function updateResults(data) {
    document.getElementById('monthlyEmi').textContent = formatCurrencyDetailed(data.emi);
    document.getElementById('principalAmount').textContent = formatCurrencyDetailed(
      parseFloat(document.getElementById('loanAmount').value)
    );
    document.getElementById('totalInterest').textContent = formatCurrencyDetailed(data.total_interest);
    document.getElementById('totalAmount').textContent = formatCurrencyDetailed(data.total_payment);
  }

  // Format currency with 2 decimals
  function formatCurrencyDetailed(num) {
    return `₹${parseFloat(num).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  // Update chart with new data
  function updateChart(principal, interest) {
    if (emiChart) {
      emiChart.updateSeries([principal, interest]);
    }
  }

  // Update amortization table
  function updateAmortizationTable(schedule) {
    if (!schedule || schedule.length === 0) return;
    const normalizedSchedule = [...schedule].sort((a, b) => {
      const aDate = new Date(a.due_date).getTime();
      const bDate = new Date(b.due_date).getTime();
      return aDate - bDate;
    });

    // Get unique years from schedule
    const years = [...new Set(normalizedSchedule.map(item => {
      const date = new Date(item.due_date);
      return isNaN(date.getTime()) ? new Date().getFullYear() : date.getFullYear();
    }))].sort((a, b) => a - b);
    
    populateYearDropdown(years, normalizedSchedule);

    // Show first year by default if not already selected
    const currentSelected = document.getElementById('selectedYear').textContent;
    if (!years.includes(parseInt(currentSelected))) {
      const firstYear = years[0];
      document.getElementById('selectedYear').textContent = firstYear;
      filterTableByYear(firstYear, normalizedSchedule);
    } else {
      filterTableByYear(currentSelected, normalizedSchedule);
    }
  }

  // Populate year dropdown
  function populateYearDropdown(years, schedule) {
    const dropdown = document.getElementById('yearDropdown');
    if (!dropdown) return;

    dropdown.innerHTML = '';
    years.forEach(year => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.className = 'dropdown-item';
      a.href = 'javascript:void(0);';
      a.textContent = year;
      a.addEventListener('click', function () {
        document.getElementById('selectedYear').textContent = year;
        filterTableByYear(year, schedule);
      });
      li.appendChild(a);
      dropdown.appendChild(li);
    });
  }

  // Filter table by selected year
  function filterTableByYear(year, schedule) {
    const tbody = document.getElementById('amortizationTableBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    const filteredSchedule = schedule.filter(item => {
      return new Date(item.due_date).getFullYear() === parseInt(year);
    });

    filteredSchedule.forEach((item, index) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${index + 1}</td>
        <td>${formatDate(item.due_date)}</td>
        <td>${formatCurrency(item.emi_amount)}</td>
        <td>${formatCurrency(item.principal)}</td>
        <td>${formatCurrency(item.interest)}</td>
        <td>${formatCurrency(item.remaining_balance)}</td>
      `;
      tbody.appendChild(row);
    });
  }

  // Format date
  function formatDate(dateString) {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return day + '-' + month + '-' + year;
  }
})();
