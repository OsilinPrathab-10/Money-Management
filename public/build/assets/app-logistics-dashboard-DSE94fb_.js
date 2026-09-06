document.addEventListener("DOMContentLoaded",function(y){let n,l,i,u,h,c;isDarkStyle?c="dark":c="light",config.colors.cardColor,n=config.colors.headingColor,l=config.colors.textMuted,u=config.colors.borderColor,h=config.colors.bodyColor,i=config.fontFamily;const d={donut:{series1:config.colors.primary},donut2:{series3:config.colors.success},line:{series1:config.colors.warning,series2:config.colors.primary}},f=document.querySelector("#shipmentStatisticsChart"),g={series:[{name:"Collected EMI",type:"column",data:[38e4,45e4,33e4,38e4,32e4,5e5,48e4,4e5,42e4,37e4]},{name:"Pending EMI",type:"line",data:[23e4,28e4,23e4,32e4,28e4,44e4,32e4,38e4,26e4,34e4]}],chart:{height:280,type:"line",stacked:!1,parentHeightOffset:0,toolbar:{show:!1},zoom:{enabled:!1}},markers:{size:5,colors:[config.colors.white],strokeColors:d.line.series2,hover:{size:6},borderRadius:4},stroke:{curve:"smooth",width:[0,3],lineCap:"round"},legend:{show:!0,position:"bottom",markers:{size:4,offsetX:-3,strokeWidth:0},height:40,itemMargin:{horizontal:8,vertical:0},fontSize:"15px",fontFamily:i,fontWeight:400,labels:{colors:n,useSeriesColors:!1},offsetY:5},grid:{strokeDashArray:8,borderColor:u},colors:[d.line.series1,d.line.series2],fill:{opacity:[1,1]},plotOptions:{bar:{columnWidth:"30%",startingShape:"rounded",endingShape:"rounded",borderRadius:4}},dataLabels:{enabled:!1},xaxis:{tickAmount:10,categories:["1 Jan","2 Jan","3 Jan","4 Jan","5 Jan","6 Jan","7 Jan","8 Jan","9 Jan","10 Jan"],labels:{style:{colors:l,fontSize:"13px",fontFamily:i,fontWeight:400}},axisBorder:{show:!1},axisTicks:{show:!1}},yaxis:{tickAmount:5,min:0,max:6e5,labels:{style:{colors:l,fontSize:"13px",fontFamily:i,fontWeight:400},formatter:function(e){return e>=1e5?"₹"+e/1e3+"K":"₹"+e}}},responsive:[{breakpoint:1400,options:{chart:{height:320},xaxis:{labels:{style:{fontSize:"10px"}}},legend:{fontSize:"13px"}}},{breakpoint:1025,options:{chart:{height:415},plotOptions:{bar:{columnWidth:"50%"}}}},{breakpoint:982,options:{plotOptions:{bar:{columnWidth:"30%"}}}},{breakpoint:480,options:{chart:{height:250},legend:{offsetY:7}}}]};typeof f!==void 0&&f!==null&&new ApexCharts(f,g).render();const p=document.querySelector("#deliveryExceptionsChart"),b={chart:{height:388,parentHeightOffset:0,type:"donut"},labels:["Personal Loan","Vehicle Loan","Home Loan","Business Loan","Educational Loan"],series:[85,62,135,98,45],colors:[config.colors.primary,config.colors.info,config.colors.warning,config.colors.success,"#8592a3"],stroke:{width:0},dataLabels:{enabled:!1,formatter:function(e,a){return parseInt(e)+"%"}},legend:{show:!0,position:"bottom",offsetY:10,markers:{size:5,width:8,height:8,strokeWidth:0},itemMargin:{horizontal:16,vertical:5},fontSize:"13px",fontFamily:i,fontWeight:400,labels:{colors:n,useSeriesColors:!1}},tooltip:{theme:c,y:{formatter:function(e){return e+" Loans"}}},grid:{padding:{top:15}},plotOptions:{pie:{donut:{size:"75%",labels:{show:!0,value:{fontSize:"26px",fontFamily:i,color:n,fontWeight:500,offsetY:-30,formatter:function(e){return parseInt(e)}},name:{offsetY:20,fontFamily:i},total:{show:!0,fontSize:"0.9375rem",label:"Total Loans",color:h,formatter:function(e){return"425"}}}}}},responsive:[{breakpoint:420,options:{chart:{height:360}}}]};typeof p!==void 0&&p!==null&&new ApexCharts(p,b).render();const m=document.querySelector(".dt-route-vehicles");m&&new DataTable(m,{ajax:assetsPath+"json/logistics-dashboard.json",columns:[{data:"id"},{data:"id",orderable:!1,render:DataTable.render.select()},{data:"location"},{data:"start_city"},{data:"end_city"},{data:"warnings"},{data:"progress"}],columnDefs:[{className:"control",orderable:!1,searchable:!1,responsivePriority:2,targets:0,render:function(e,a,s,t){return""}},{targets:1,orderable:!1,searchable:!1,responsivePriority:3,checkboxes:!0,render:function(){return'<input type="checkbox" class="dt-checkboxes form-check-input">'},checkboxes:{selectAllRender:'<input type="checkbox" class="form-check-input">'}},{targets:2,responsivePriority:1,render:(e,a,s)=>{const t=s.location;return`
                  <div class="d-flex justify-content-start align-items-center user-name">
                      <div class="avatar-wrapper">
                          <div class="avatar me-3">
                              <span class="avatar-initial rounded-circle bg-label-secondary">
                                  <i class="icon-base ri ri-car-line icon-28px"></i>
                              </span>
                          </div>
                      </div>
                      <div class="d-flex flex-column">
                        <a class="text-heading text-nowrap fw-medium" href="${baseUrl}app/logistics/fleet">VOL-${t}</a>
                      </div>
                  </div>
              `}},{targets:3,render:(e,a,s)=>{const{start_city:t,start_country:o}=s;return`
                  <div class="text-body">
                      ${t}, ${o}
                  </div>
              `}},{targets:4,render:(e,a,s)=>{const{end_city:t,end_country:o}=s;return`
                  <div class="text-body">
                      ${t}, ${o}
                  </div>
              `}},{targets:-2,render:(e,a,s)=>{const t=s.warnings,r={1:{title:"No Warnings",class:"bg-label-success"},2:{title:"Temperature Not Optimal",class:"bg-label-warning"},3:{title:"Ecu Not Responding",class:"bg-label-danger"},4:{title:"Oil Leakage",class:"bg-label-info"},5:{title:"Fuel Problems",class:"bg-label-primary"}}[t];return r?`
                  <span class="badge rounded-pill ${r.class}">
                      ${r.title}
                  </span>
              `:e}},{targets:-1,render:(e,a,s)=>{const t=s.progress;return`
                  <div class="d-flex align-items-center">
                      <div class="progress bg-label-primary w-100" style="height: 8px;">
                          <div
                              class="progress-bar"
                              role="progressbar"
                              style="width: ${t}%"
                              aria-valuenow="${t}"
                              aria-valuemin="0"
                              aria-valuemax="100">
                          </div>
                      </div>
                      <div class="text-body ms-2">${t}%</div>
                  </div>
              `}}],select:{style:"multi",selector:"td:nth-child(2)"},order:[2,"asc"],layout:{topStart:{rowClass:"",features:[]},topEnd:{},bottomStart:{rowClass:"row mx-3 justify-content-between",features:["info"]},bottomEnd:"paging"},lengthMenu:[5],language:{paginate:{next:'<i class="icon-base ri ri-arrow-right-s-line scaleX-n1-rtl icon-22px"></i>',previous:'<i class="icon-base ri ri-arrow-left-s-line scaleX-n1-rtl icon-22px"></i>',first:'<i class="icon-base ri ri-skip-back-mini-line scaleX-n1-rtl icon-22px"></i>',last:'<i class="icon-base ri ri-skip-forward-mini-line scaleX-n1-rtl icon-22px"></i>'}},responsive:{details:{display:DataTable.Responsive.display.modal({header:function(e){return"Details of "+e.data().location}}),type:"column",renderer:function(e,a,s){const t=s.map(function(o){return o.title!==""?`<tr data-dt-row="${o.rowIndex}" data-dt-column="${o.columnIndex}">
                      <td>${o.title}:</td>
                      <td>${o.data}</td>
                    </tr>`:""}).join("");if(t){const o=document.createElement("table");o.classList.add("table","datatables-basic","mb-2");const r=document.createElement("tbody");return r.innerHTML=t,o.appendChild(r),o}return!1}}}}),setTimeout(()=>{[{selector:".dt-layout-start",classToAdd:"my-0"},{selector:".dt-layout-end",classToAdd:"my-0"},{selector:".dt-layout-table",classToRemove:"row mt-2",classToAdd:"mt-n2"},{selector:".dt-layout-full",classToRemove:"col-md col-12",classToAdd:"table-responsive"}].forEach(({selector:a,classToRemove:s,classToAdd:t})=>{document.querySelectorAll(a).forEach(o=>{s&&s.split(" ").forEach(r=>o.classList.remove(r)),t&&t.split(" ").forEach(r=>o.classList.add(r))})})},100)});
