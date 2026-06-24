(()=>{
  const chartPalette=["rgba(164,112,135,0.8)","rgba(112,148,166,0.8)","rgba(135,157,111,0.8)","rgba(198,154,102,0.8)","rgba(137,125,174,0.8)","rgba(101,157,148,0.8)","rgba(184,122,118,0.8)","rgba(151,139,105,0.8)","rgba(108,132,178,0.8)","rgba(177,129,160,0.8)","rgba(116,160,121,0.8)","rgba(187,147,91,0.8)"];
  let progressSaveTimer=null;
  function qs(s,r=document){return r.querySelector(s)}
  function qsa(s,r=document){return Array.from(r.querySelectorAll(s))}
  function nowLocalValue(){const d=new Date();d.setMinutes(d.getMinutes()-d.getTimezoneOffset());return d.toISOString().slice(0,16)}
  function oneLine(v){return String(v||"").replace(/[\r\n]+/g," ").replace(/\s+/g," ").trim().slice(0,1000)}
  function normalizeProgressTarget(t){
    const row=t.closest?.(".rpm-toc-row");
    const input=row?.querySelector(".rpm-percent-input");
    const chk=row?.querySelector(".rpm-progress-check");
    const completed=row?.querySelector(".rpm-item-completed-input");
    if(t.matches?.(".rpm-progress-check")&&input){
      if(t.checked){input.value="100";if(completed&&!completed.value)completed.value=nowLocalValue()}
      else{if(input.value==="100")input.value="0";if(completed)completed.value=""}
    }
    if(t.matches?.(".rpm-percent-input")){
      let v=parseInt(t.value||"0",10);if(Number.isNaN(v))v=0;v=Math.min(100,Math.max(0,v));t.value=String(v);
      if(chk)chk.checked=v===100;
      if(completed){if(v===100&&!completed.value)completed.value=nowLocalValue();if(v!==100)completed.value=""}
    }
  }
  function showSaving(form){const status=qs("#rpm-autosave-status",form);if(status)status.innerHTML='<span class="rpm-autosave-saving">保存中...</span>'}
  function requestAutoSave(form,delay=500){if(!form)return;clearTimeout(progressSaveTimer);progressSaveTimer=setTimeout(()=>{if(typeof htmx==="undefined")return;if(form.dataset.saving==="1"){form.dataset.pending="1";return}showSaving(form);form.dataset.saving="1";htmx.trigger(form,"submit")},delay)}
  function refreshMemoUi(id,value){
    const hidden=document.getElementById("rpm-memo-hidden-"+id);
    const button=document.getElementById("rpm-memo-button-"+id);
    const row=hidden?.closest(".rpm-toc-row");
    const title=row?.querySelector(".rpm-toc-title");
    if(button){button.textContent=value?"メモあり":"メモ";button.classList.toggle("has-memo",!!value)}
    if(title){if(value){title.setAttribute("uk-tooltip",value)}else{title.removeAttribute("uk-tooltip")}}
  }
  function saveMemoInput(input){
    const id=input?.dataset?.memoFor;
    if(!id)return;
    const form=qs("#rpm-progress-form");
    const hidden=document.getElementById("rpm-memo-hidden-"+id);
    if(!hidden)return;
    const value=oneLine(input.value);
    input.value=value;
    if(hidden.value!==value){
      hidden.value=value;
      refreshMemoUi(id,value);
      requestAutoSave(form,120);
    }
  }
  function bindProgressForm(){
    const form=qs("#rpm-progress-form");
    if(!form||form.dataset.bound==="1")return;
    form.dataset.bound="1";
    form.addEventListener("change",ev=>{const t=ev.target;if(!(t instanceof HTMLElement))return;normalizeProgressTarget(t);if(t.matches(".rpm-progress-check,.rpm-percent-input,.rpm-item-completed-input")){requestAutoSave(form,120);return}if(t.matches("input,select,textarea")){requestAutoSave(form,400)}});
    form.addEventListener("input",ev=>{const t=ev.target;if(!(t instanceof HTMLElement))return;if(t.matches(".rpm-percent-input")){normalizeProgressTarget(t);requestAutoSave(form,420)}});
    form.addEventListener("submit",()=>{if(typeof htmx!=="undefined")showSaving(form)})
  }
  function readChartData(){const el=document.getElementById("progress-chart-data");if(!el)return null;try{return JSON.parse(el.textContent||"{}")}catch(e){console.warn(e);return null}}
  function readMindmapData(){const el=document.getElementById("rpm-mindmap-data");if(!el)return null;try{return JSON.parse(el.textContent||"{}")}catch(e){console.warn(e);return null}}
  function baseChartFont(){return {fontFamily:'"Klee One","Hiragino Sans","Yu Gothic",sans-serif'}}
  function optionFor(kind,data){
    if(kind==="polar")return {color:chartPalette,textStyle:baseChartFont(),tooltip:{trigger:"item",formatter:"{b}: {c}%"},angleAxis:{max:100,startAngle:75},radiusAxis:{type:"category",data:data.chapterLabels||[]},polar:{},series:[{type:"bar",data:data.chapterPercents||[],coordinateSystem:"polar",label:{show:true,position:"middle",formatter:"{b}: {c}%"},itemStyle:{color:p=>chartPalette[p.dataIndex%chartPalette.length]}}]};
    if(kind==="stacked"){const done=data.chapterPercents||[];const remain=data.chapterRemaining||done.map(v=>Math.max(0,100-v));return {color:[chartPalette[0],chartPalette[3],chartPalette[6]],textStyle:baseChartFont(),tooltip:{trigger:"axis"},legend:{data:["完成度","残り"]},xAxis:{type:"category",boundaryGap:false,data:data.chapterLabels||[]},yAxis:{type:"value",max:100,axisLabel:{formatter:"{value}%"}},series:[{name:"完成度",type:"line",areaStyle:{opacity:.22},smooth:true,data:done},{name:"残り",type:"line",areaStyle:{opacity:.22},smooth:true,data:remain}]}}
    return {color:[chartPalette[4],chartPalette[2],chartPalette[8]],textStyle:baseChartFont(),tooltip:{trigger:"axis"},legend:{data:["達成数","総進展"]},xAxis:[{type:"category",data:data.bucketLabels||[]}],yAxis:[{type:"value",name:"達成数",minInterval:1},{type:"value",name:"総進展",min:0,max:100,axisLabel:{formatter:"{value}%"}}],series:[{name:"達成数",type:"bar",data:data.bucketDelta||[]},{name:"総進展",type:"line",yAxisIndex:1,smooth:true,data:data.bucketTotal||[]}]};
  }
  function initOneChart(el,kind,data){if(!el||typeof echarts==="undefined")return;const old=echarts.getInstanceByDom(el);if(old)old.dispose();const chart=echarts.init(el);chart.setOption(optionFor(kind,data));setTimeout(()=>chart.resize(),80)}
  function initCharts(){if(typeof echarts==="undefined")return;const data=readChartData();if(!data)return;initOneChart(qs("#chart-polar"),"polar",data);initOneChart(qs("#chart-polar-modal"),"polar",data);initOneChart(qs("#chart-stacked"),"stacked",data);initOneChart(qs("#chart-stacked-modal"),"stacked",data);initOneChart(qs("#chart-mixed"),"mixed",data);initOneChart(qs("#chart-mixed-modal"),"mixed",data)}
  function initMindmap(){if(typeof echarts==="undefined")return;const el=qs("#chart-mindmap");const data=readMindmapData();if(!el||!data)return;if(el.offsetWidth===0&&el.offsetHeight===0)return;const old=echarts.getInstanceByDom(el);if(old)old.dispose();const chart=echarts.init(el);chart.setOption({color:chartPalette,textStyle:baseChartFont(),tooltip:{trigger:"item",triggerOn:"mousemove"},series:[{type:"tree",name:"From Left to Right Tree",data:[data],left:"8%",right:"22%",top:"8%",bottom:"8%",orient:"LR",symbolSize:10,edgeShape:"polyline",initialTreeDepth:2,expandAndCollapse:true,animationDuration:420,animationDurationUpdate:620,label:{position:"left",verticalAlign:"middle",align:"right",fontSize:13,color:"#4b1236"},leaves:{label:{position:"right",verticalAlign:"middle",align:"left",fontSize:13,color:"#4b1236"}},emphasis:{focus:"descendant"},lineStyle:{color:"rgba(110,30,81,0.32)",width:2}}]});setTimeout(()=>chart.resize(),80)}
  function syncMindmapDetails(){qsa(".rpm-mindmap-summary").forEach(el=>{if(!el.dataset.responsiveInit){if(window.matchMedia&&window.matchMedia("(orientation: portrait)").matches)el.removeAttribute("open");else el.setAttribute("open","open");el.dataset.responsiveInit="1"}if(!el.dataset.bound){el.dataset.bound="1";el.addEventListener("toggle",()=>{if(el.open)setTimeout(()=>{initMindmap();resizeCharts()},80)})}})}
  function resizeCharts(){if(typeof echarts==="undefined")return;qsa(".rpm-chart,.rpm-mindmap-chart").forEach(el=>{const c=echarts.getInstanceByDom(el);if(c)c.resize()})}
  document.addEventListener("DOMContentLoaded",()=>{bindProgressForm();syncMindmapDetails();initCharts();initMindmap();window.addEventListener("resize",resizeCharts,{passive:true})});
  document.body.addEventListener("beforeshow",ev=>{if(ev.target?.classList?.contains("rpm-memo-modal")){const input=ev.target.querySelector(".rpm-memo-modal-input");setTimeout(()=>input?.focus(),60)}});
  document.body.addEventListener("hide",ev=>{if(ev.target?.classList?.contains("rpm-memo-modal")){saveMemoInput(ev.target.querySelector(".rpm-memo-modal-input"))}});
  document.addEventListener("keydown",ev=>{const t=ev.target;if(t instanceof HTMLElement&&t.matches(".rpm-memo-modal-input")&&ev.key==="Enter"){ev.preventDefault();saveMemoInput(t);if(window.UIkit){const modal=t.closest(".rpm-memo-modal");if(modal)UIkit.modal(modal).hide()}}});
  document.body.addEventListener("shown",ev=>{if(ev.target?.classList?.contains("rpm-chart-modal")){setTimeout(()=>{initCharts();resizeCharts()},120);setTimeout(resizeCharts,350)}});
  document.body.addEventListener("htmx:afterRequest",ev=>{const form=qs("#rpm-progress-form");if(!form)return;if(ev.detail&&ev.detail.elt===form){form.dataset.saving="0";if(form.dataset.pending==="1"){form.dataset.pending="0";requestAutoSave(form,120)}}});
  document.body.addEventListener("htmx:afterSettle",()=>{bindProgressForm();syncMindmapDetails();initCharts();initMindmap();resizeCharts()})
})();
