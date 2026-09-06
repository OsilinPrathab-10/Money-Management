'use strict';

document.addEventListener('DOMContentLoaded', function () {
  const data = window.dashboardData || {};
  const emiChartData = data.emiChart || {};
  const loanPerformanceChartData = data.loanPerformanceChart || {};
  const loanDistributionChartData = data.loanDistributionChart || {};
  const currencySymbol = data.currencySymbol || '₹';

  const cardColor = config.colors.cardColor;
  const headingColor = config.colors.headingColor;
  const labelColor = config.colors.textMuted;
  const borderColor = config.colors.borderColor;
  const fontFamily = config.fontFamily;

  const isDark = typeof isDarkStyle !== 'undefined' ? isDarkStyle : false;
  const tooltipTheme = isDark ? 'dark' : 'light';

  const formatCurrency = value => {
    if (value >= 10000000) {
      return `${currencySymbol}${(value / 10000000).toFixed(1)}Cr`;
    }

    if (value >= 100000) {
      return `${currencySymbol}${(value / 100000).toFixed(1)}L`; // lakhs
    }

    if (value >= 1000) {
      return `${currencySymbol}${(value / 1000).toFixed(1)}K`;
    }

    return `${currencySymbol}${value.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
  };

  const emiElement = document.querySelector('#emiCollectionChart');
  const emiMonthDropdown = document.querySelector('#emiMonthDropdown');
  const emiMonthDropdownMenu = document.querySelector('#emiMonthDropdownMenu');
  const emiDefaultLabel = emiMonthDropdown?.dataset?.defaultLabel || 'All Months';

  let emiChartInstance = null;
  let loanDistributionChartInstance = null;

  const buildEmiChartConfig = (labels = [], collectedSeries = [], pendingSeries = []) => {
    // Ensure all parameters are arrays
    const validLabels = Array.isArray(labels) ? labels : [];
    const validCollected = Array.isArray(collectedSeries) ? collectedSeries.map(v => Number(v || 0)) : [];
    const validPending = Array.isArray(pendingSeries) ? pendingSeries.map(v => Number(v || 0)) : [];

    return {
    series: [
      {
        name: 'Collected EMI',
        type: 'column',
        data: validCollected
      },
      {
        name: 'Pending EMI',
        type: 'line',
        data: validPending
      }
    ],
    chart: {
      height: 320,
      type: 'line',
      stacked: false,
      parentHeightOffset: 0,
      toolbar: { show: false }
    },
    dataLabels: { enabled: false },
    stroke: {
      curve: 'smooth',
      width: [0, 3],
      lineCap: 'round'
    },
    legend: {
      show: true,
      position: 'bottom',
      offsetY: 5,
      markers: { width: 8, height: 8, offsetX: -3 },
      labels: { colors: headingColor },
      fontFamily,
      fontSize: '14px'
    },
    markers: {
      size: 5,
      colors: [config.colors.white],
      strokeColors: [config.colors.primary],
      hover: { size: 7 }
    },
    colors: [config.colors.primary, config.colors.warning],
    fill: { opacity: [1, 0.6] },
    plotOptions: {
      bar: {
        columnWidth: '35%',
        borderRadius: 6,
        startingShape: 'rounded',
        endingShape: 'rounded'
      }
    },
    grid: { borderColor, strokeDashArray: 8 },
    tooltip: {
      shared: true,
      theme: tooltipTheme,
      y: {
        formatter(value) {
          return formatCurrency(value);
        }
      }
    },
    xaxis: {
      categories: validLabels,
      labels: {
        style: {
          colors: labelColor,
          fontFamily,
          fontSize: '13px'
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      min: 0,
      labels: {
        style: {
          colors: labelColor,
          fontFamily,
          fontSize: '13px'
        },
        formatter(value) {
          return formatCurrency(value);
        }
      }
    }
    };
  };

  const updateEmiChartDisplay = selectedMonth => {
    if (!emiChartInstance || !emiChartData) return;

    // Ensure arrays are proper arrays, not objects or undefined
    const labels = Array.isArray(emiChartData.labels) ? emiChartData.labels : [];
    const collected = Array.isArray(emiChartData.collected) ? emiChartData.collected : [];
    const pending = Array.isArray(emiChartData.pending) ? emiChartData.pending : [];

    // If no data, don't update
    if (labels.length === 0) return;

    let nextLabels = labels;
    let nextCollected = collected;
    let nextPending = pending;
    let buttonLabel = emiDefaultLabel;

    if (selectedMonth !== 'all') {
      const monthIndex = Number(selectedMonth);

      if (!Number.isNaN(monthIndex) && monthIndex >= 0 && monthIndex < labels.length && labels[monthIndex]) {
        nextLabels = [labels[monthIndex]];
        nextCollected = [Number(collected[monthIndex] || 0)];
        nextPending = [Number(pending[monthIndex] || 0)];
        buttonLabel = labels[monthIndex];
      }
    }

    try {
      emiChartInstance.updateOptions({
        xaxis: { categories: nextLabels }
      });

      emiChartInstance.updateSeries([
        { name: 'Collected EMI', type: 'column', data: nextCollected },
        { name: 'Pending EMI', type: 'line', data: nextPending }
      ]);

      if (emiMonthDropdown) {
        emiMonthDropdown.textContent = buttonLabel;
      }
    } catch (error) {
      console.error('Error updating EMI chart:', error);
    }
  };

  const registerEmiMonthFilter = () => {
    if (!emiMonthDropdownMenu) return;

    emiMonthDropdownMenu.addEventListener('click', event => {
      const target = event.target.closest('.dropdown-item');
      if (!target) return;

      event.preventDefault();

      const selectedMonth = target.getAttribute('data-month') || 'all';

      emiMonthDropdownMenu.querySelectorAll('.dropdown-item').forEach(item => {
        item.classList.toggle('active', item === target);
      });

      updateEmiChartDisplay(selectedMonth);
    });
  };

  if (emiElement && emiChartData && emiChartData.hasData) {
    emiChartInstance = new ApexCharts(
      emiElement,
      buildEmiChartConfig(emiChartData.labels, emiChartData.collected, emiChartData.pending)
    );

    emiChartInstance.render().then(() => {
      registerEmiMonthFilter();
      updateEmiChartDisplay('all');
    }).catch(error => {
      console.error('Error rendering EMI chart:', error);
    });
  }

  // Loan performance (bar)
  // Loan performance list is rendered server-side; no chart setup required.

  // Loan distribution (donut)
  const loanDistributionElement = document.querySelector('#loanDistributionChart');

  if (loanDistributionElement && loanDistributionChartData.hasData) {
    const totalLoans = loanDistributionChartData.total ?? (loanDistributionChartData.series || []).reduce((sum, value) => sum + value, 0);

    loanDistributionChartInstance = new ApexCharts(loanDistributionElement, {
      chart: {
        type: 'donut',
        height: 340
      },
      labels: loanDistributionChartData.labels || [],
      series: loanDistributionChartData.series || [],
      colors: [
        config.colors.primary,
        config.colors.success,
        config.colors.info,
        config.colors.warning,
        config.colors.danger,
        '#8592a3'
      ],
      stroke: { width: 0 },
      dataLabels: {
        enabled: true,
        formatter(val) {
          return `${val.toFixed(1)}%`;
        }
      },
      legend: {
        show: true,
        position: 'bottom',
        fontFamily,
        fontSize: '13px',
        labels: { colors: headingColor }
      },
      tooltip: {
        theme: tooltipTheme,
        y: {
          formatter(value) {
            return `${value} Loans`;
          }
        }
      },
      plotOptions: {
        pie: {
          donut: {
            size: '70%',
            labels: {
              show: true,
              name: {
                color: labelColor,
                fontFamily
              },
              value: {
                fontSize: '24px',
                fontFamily,
                color: headingColor,
                formatter(value) {
                  return value;
                }
              },
              total: {
                show: true,
                label: 'Total Loans',
                color: labelColor,
                formatter() {
                  return totalLoans;
                }
              }
            }
          }
        }
      },
      states: {
        hover: { filter: { type: 'lighten', value: 0.05 } },
        active: { filter: { type: 'darken', value: 0.1 } }
      }
    });

    loanDistributionChartInstance.render();
  }

  // Date Range Filter Logic & Flatpickr Initialization
  const customStartDateInput = document.getElementById('customStartDate');
  const customEndDateInput = document.getElementById('customEndDate');
  const customDateContainer = document.getElementById('customDateContainer');

  if (typeof flatpickr !== 'undefined') {
    if (customStartDateInput) {
      flatpickr(customStartDateInput, {
        dateFormat: 'Y-m-d',
        allowInput: true
      });
    }
    if (customEndDateInput) {
      flatpickr(customEndDateInput, {
        dateFormat: 'Y-m-d',
        allowInput: true
      });
    }
  }

  const initialDateRange = data.dateRange || 'all';
  if (initialDateRange === 'custom' && customDateContainer) {
    customDateContainer.classList.remove('d-none');
    customDateContainer.classList.add('d-flex');
  }

  const filterMenu = document.getElementById('dashboardDateFilterMenu');
  if (filterMenu) {
    filterMenu.addEventListener('click', function (e) {
      const target = e.target.closest('.dropdown-item');
      if (!target) return;

      e.preventDefault();
      const range = target.getAttribute('data-range');

      if (range === 'custom') {
        if (customDateContainer) {
          customDateContainer.classList.remove('d-none');
          customDateContainer.classList.add('d-flex');
        }
      } else {
        if (customDateContainer) {
          customDateContainer.classList.remove('d-flex');
          customDateContainer.classList.add('d-none');
        }
        const searchParams = new URLSearchParams(window.location.search);
        searchParams.set('date_range', range);
        searchParams.delete('start_date');
        searchParams.delete('end_date');
        window.location.search = searchParams.toString();
      }
    });
  }

  const applyCustomBtn = document.getElementById('applyCustomDateBtn');
  if (applyCustomBtn) {
    applyCustomBtn.addEventListener('click', function () {
      const startDate = customStartDateInput ? customStartDateInput.value.trim() : '';
      const endDate = customEndDateInput ? customEndDateInput.value.trim() : '';

      if (!startDate || !endDate) {
        alert('Please select both start date and end date.');
        return;
      }

      const searchParams = new URLSearchParams(window.location.search);
      searchParams.set('date_range', 'custom');
      searchParams.set('start_date', startDate);
      searchParams.set('end_date', endDate);
      window.location.search = searchParams.toString();
    });
  }

  // Real-time Statistics Refresh
  const refreshBtn = document.getElementById('refreshStatsBtn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function () {
      const btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> refreshing...';

      setTimeout(() => {
        window.location.reload();
      }, 400);
    });
  }
});
