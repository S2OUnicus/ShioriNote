(()=>{
  const chartPalette=["rgba(164,112,135,0.8)","rgba(112,148,166,0.8)","rgba(135,157,111,0.8)","rgba(198,154,102,0.8)","rgba(137,125,174,0.8)","rgba(101,157,148,0.8)","rgba(184,122,118,0.8)","rgba(151,139,105,0.8)","rgba(108,132,178,0.8)","rgba(177,129,160,0.8)","rgba(116,160,121,0.8)","rgba(187,147,91,0.8)"];
  let progressSaveTimer=null;
  function configureHtmx(){if(window.htmx){htmx.config.allowScriptTags=false;htmx.config.allowEval=false}}
  function qs(s,r=document){return r.querySelector(s)}
  function qsa(s,r=document){return Array.from(r.querySelectorAll(s))}
  function nowLocalValue(){const d=new Date();d.setMinutes(d.getMinutes()-d.getTimezoneOffset());return d.toISOString().slice(0,16)}
  function oneLine(v){return String(v||"").replace(/[\r\n]+/g," ").replace(/\s+/g," ").trim().slice(0,1000)}
  function isPortrait(){return window.matchMedia&&window.matchMedia("(orientation: portrait)").matches}
  function normalizeStyle(v){return v==="horizontal"?"horizontal":"rounded"}
  function currentChapterStyle(){const z=qs("#rpm-chart-zone");return normalizeStyle(z?.dataset?.currentStyle||z?.dataset?.chapterStyle||readChartData()?.chapterChartStyle||"rounded")}
  function setCurrentChapterStyle(style){style=normalizeStyle(style);const z=qs("#rpm-chart-zone");if(z)z.dataset.currentStyle=style;qsa(".rpm-chart-toggle").forEach(b=>{b.textContent=style==="horizontal"?"横棒":"丸棒"});return style}
  function activeChapterIndex(){const active=qs(".rpm-chapter-tabs>li.uk-active>a[data-chapter-index]");const v=active?.dataset?.chapterIndex||qs("#rpm-active-chapter")?.value||qs("#rpm-toc-section")?.dataset?.activeChapter||"0";const n=parseInt(v,10);return Number.isFinite(n)&&n>=0?n:0}
  function setActiveChapterIndex(i){const h=qs("#rpm-active-chapter");if(h)h.value=String(i);const s=qs("#rpm-toc-section");if(s)s.dataset.activeChapter=String(i)}
  function applyActiveChapter(){const section=qs("#rpm-toc-section");if(!section)return;const idx=parseInt(section.dataset.activeChapter||activeChapterIndex()||0,10)||0;const tabList=qs(".rpm-chapter-tabs");const tabs=qsa(".rpm-chapter-tabs>li");const panes=qsa(".rpm-chapter-switcher>li");if(!tabs.length||!panes.length)return;tabs.forEach((li,i)=>li.classList.toggle("uk-active",i===idx));panes.forEach((li,i)=>li.classList.toggle("uk-active",i===idx));if(window.UIkit&&tabList){try{UIkit.tab(tabList).show(idx)}catch(e){}}setActiveChapterIndex(idx)}
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
  function requestAutoSave(form,delay=500){if(!form)return;setActiveChapterIndex(activeChapterIndex());clearTimeout(progressSaveTimer);progressSaveTimer=setTimeout(()=>{if(typeof htmx==="undefined")return;configureHtmx();setActiveChapterIndex(activeChapterIndex());if(form.dataset.saving==="1"){form.dataset.pending="1";return}showSaving(form);form.dataset.saving="1";htmx.trigger(form,"submit")},delay)}
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
  function readJsonElement(id){const el=document.getElementById(id);if(!el)return null;let text="";if(el.tagName==="TEMPLATE")text=el.innerHTML||"";else text=el.textContent||"";try{return JSON.parse(text||"{}")}catch(e){console.warn(e);return null}}
  function readChartData(){return readJsonElement("progress-chart-data")}
  function readMindmapData(){return readJsonElement("rpm-mindmap-data")}
  function baseChartFont(){return {fontFamily:'"Klee One","Hiragino Sans","Yu Gothic",sans-serif'}}
  function roundedOption(data){return {color:chartPalette,textStyle:baseChartFont(),tooltip:{trigger:"item",formatter:"{b}: {c}%"},angleAxis:{max:100,startAngle:75},radiusAxis:{type:"category",data:data.chapterLabels||[]},polar:{},series:[{type:"bar",data:data.chapterPercents||[],coordinateSystem:"polar",roundCap:true,label:{show:true,position:"middle",formatter:"{b}: {c}%"},itemStyle:{color:p=>chartPalette[p.dataIndex%chartPalette.length]}}]}}
  function horizontalOption(data){return {color:chartPalette,textStyle:baseChartFont(),grid:{left:54,right:28,top:28,bottom:40,containLabel:true},tooltip:{trigger:"axis",axisPointer:{type:"shadow"},formatter:items=>{const p=items&&items[0];return p?`${p.name}: ${p.value}%`:""}},xAxis:{type:"value",min:0,max:100,axisLabel:{formatter:"{value}%"}},yAxis:{type:"category",inverse:true,data:data.chapterLabels||[]},series:[{name:"完成度",type:"bar",data:data.chapterPercents||[],barWidth:18,itemStyle:{borderRadius:[0,12,12,0],color:p=>chartPalette[p.dataIndex%chartPalette.length]},label:{show:true,position:"right",formatter:"{c}%"}}]}}
  function optionFor(kind,data){
    if(kind==="rounded"||kind==="polar")return roundedOption(data);
    if(kind==="horizontal")return horizontalOption(data);
    if(kind==="stacked"){const done=data.chapterPercents||[];const remain=data.chapterRemaining||done.map(v=>Math.max(0,100-v));return {color:[chartPalette[0],chartPalette[3],chartPalette[6]],textStyle:baseChartFont(),tooltip:{trigger:"axis"},legend:{data:["完成度","残り"]},xAxis:{type:"category",boundaryGap:false,data:data.chapterLabels||[]},yAxis:{type:"value",max:100,axisLabel:{formatter:"{value}%"}},series:[{name:"完成度",type:"line",areaStyle:{opacity:.22},smooth:true,data:done},{name:"残り",type:"line",areaStyle:{opacity:.22},smooth:true,data:remain}]}}
    return {color:[chartPalette[4],chartPalette[2],chartPalette[8]],textStyle:baseChartFont(),tooltip:{trigger:"axis"},legend:{data:["達成数","総進展"]},xAxis:[{type:"category",data:data.bucketLabels||[]}],yAxis:[{type:"value",name:"達成数",minInterval:1},{type:"value",name:"総進展",min:0,max:100,axisLabel:{formatter:"{value}%"}}],series:[{name:"達成数",type:"bar",data:data.bucketDelta||[]},{name:"総進展",type:"line",yAxisIndex:1,smooth:true,data:data.bucketTotal||[]}]};
  }
  function initOneChart(el,kind,data){if(!el||typeof echarts==="undefined")return;const old=echarts.getInstanceByDom(el);if(old)old.dispose();const chart=echarts.init(el);chart.setOption(optionFor(kind,data));setTimeout(()=>chart.resize(),80)}
  function initCharts(){if(typeof echarts==="undefined")return;const data=readChartData();if(!data)return;const style=setCurrentChapterStyle(currentChapterStyle());initOneChart(qs("#chart-polar"),style,data);initOneChart(qs("#chart-stacked"),"stacked",data);initOneChart(qs("#chart-mixed"),"mixed",data)}
  function initModalChart(modal){
    if(typeof echarts==="undefined")return;const data=readChartData();if(!data||!modal)return;
    if(modal.id==="modal-chart-chapter"){
      initOneChart(modal.querySelector("#chart-chapter-rounded-modal"),"rounded",data);
      initOneChart(modal.querySelector("#chart-chapter-horizontal-modal"),"horizontal",data);
      initOneChart(modal.querySelector("#chart-chapter-mobile-modal"),currentChapterStyle(),data);
    }else{
      const pairs=[["chart-stacked-modal","stacked"],["chart-mixed-modal","mixed"]];
      pairs.forEach(([id,kind])=>{const el=modal.querySelector("#"+id);if(el){initOneChart(el,kind,data)}})
    }
    setTimeout(resizeCharts,160);setTimeout(resizeCharts,420)
  }
  function toggleChapterChart(area){const style=setCurrentChapterStyle(currentChapterStyle()==="horizontal"?"rounded":"horizontal");const data=readChartData();if(!data)return;initOneChart(qs("#chart-polar"),style,data);if(area==="modal")initOneChart(qs("#chart-chapter-mobile-modal"),style,data);resizeCharts()}
  function initMindmap(){if(typeof echarts==="undefined")return;const el=qs("#chart-mindmap");const raw=readMindmapData();if(!el||!raw)return;if(el.offsetWidth===0&&el.offsetHeight===0)return;const nodes=(Array.isArray(raw)?raw:[raw]).filter(n=>n&&typeof n==="object"&&String(n.name||"").trim()!=="");if(!nodes.length){el.innerHTML='<p class="rpm-muted uk-padding-small">表示できる目次がありません。</p>';return}const old=echarts.getInstanceByDom(el);if(old)old.dispose();const chart=echarts.init(el);chart.setOption({color:chartPalette,textStyle:baseChartFont(),tooltip:{trigger:"item",triggerOn:"mousemove",formatter:p=>p?.data?.name||""},series:[{type:"tree",name:"目次マインドマップ",data:nodes,top:"5%",left:"7%",bottom:"5%",right:"20%",orient:"LR",symbolSize:(value,params)=>params?.data?.name?8:0,roam:true,initialTreeDepth:2,expandAndCollapse:true,animationDuration:550,animationDurationUpdate:750,label:{position:"left",verticalAlign:"middle",align:"right",fontSize:13,color:"#4b1236",lineHeight:18,formatter:p=>p?.data?.name||p?.name||""},leaves:{label:{position:"right",verticalAlign:"middle",align:"left",fontSize:13,color:"#4b1236",lineHeight:18,formatter:p=>p?.data?.name||p?.name||""}},emphasis:{focus:"descendant"},lineStyle:{color:"rgba(110,30,81,0.32)",width:2,curveness:.35}}]});setTimeout(()=>chart.resize(),80);setTimeout(()=>chart.resize(),260)}
  function syncMindmapDetails(){qsa(".rpm-mindmap-summary").forEach(el=>{if(!el.dataset.responsiveInit){if(isPortrait())el.removeAttribute("open");else el.setAttribute("open","open");el.dataset.responsiveInit="1"}if(!el.dataset.bound){el.dataset.bound="1";el.addEventListener("toggle",()=>{if(el.open)setTimeout(()=>{initMindmap();resizeCharts()},80)})}})}
  function resizeCharts(){if(typeof echarts==="undefined")return;qsa(".rpm-chart,.rpm-mindmap-chart").forEach(el=>{const c=echarts.getInstanceByDom(el);if(c)c.resize()})}
  function bindChapterTabs(){qsa(".rpm-chapter-tabs a[data-chapter-index]").forEach(a=>{if(a.dataset.boundTab==="1")return;a.dataset.boundTab="1";a.addEventListener("click",()=>{setActiveChapterIndex(parseInt(a.dataset.chapterIndex||"0",10)||0)})})}
  function bindConfirmForms(){qsa("form[data-confirm]").forEach(f=>{if(f.dataset.boundConfirm==="1")return;f.dataset.boundConfirm="1";f.addEventListener("submit",ev=>{if(!confirm(f.dataset.confirm||"実行しますか？")){ev.preventDefault()}})})}
  function bindImagePreview(){qsa("input[type=file][data-preview-target]").forEach(input=>{if(input.dataset.boundPreview==="1")return;input.dataset.boundPreview="1";input.addEventListener("change",()=>{const img=qs(input.dataset.previewTarget);const file=input.files&&input.files[0];if(!img||!file)return;if(!file.type.startsWith("image/"))return;const url=URL.createObjectURL(file);img.src=url;img.onload=()=>URL.revokeObjectURL(url)})})}

  document.addEventListener("DOMContentLoaded",()=>{configureHtmx();bindProgressForm();bindChapterTabs();bindConfirmForms();bindImagePreview();applyActiveChapter();syncMindmapDetails();initCharts();initMindmap();window.addEventListener("resize",resizeCharts,{passive:true})});
  document.body.addEventListener("click",ev=>{const btn=ev.target?.closest?.(".rpm-chart-toggle");if(btn){ev.preventDefault();ev.stopPropagation();toggleChapterChart(btn.dataset.chartArea||"main")}});
  document.body.addEventListener("beforeshow",ev=>{if(ev.target?.classList?.contains("rpm-memo-modal")){const input=ev.target.querySelector(".rpm-memo-modal-input");setTimeout(()=>input?.focus(),60)}});
  document.body.addEventListener("hide",ev=>{if(ev.target?.classList?.contains("rpm-memo-modal")){saveMemoInput(ev.target.querySelector(".rpm-memo-modal-input"))}});
  document.addEventListener("keydown",ev=>{const t=ev.target;if(t instanceof HTMLElement&&t.matches(".rpm-memo-modal-input")&&ev.key==="Enter"){ev.preventDefault();saveMemoInput(t);if(window.UIkit){const modal=t.closest(".rpm-memo-modal");if(modal)UIkit.modal(modal).hide()}}});
  document.body.addEventListener("shown",ev=>{if(ev.target?.classList?.contains("rpm-chart-modal")){setTimeout(()=>{initModalChart(ev.target);resizeCharts()},80);setTimeout(()=>{initModalChart(ev.target);resizeCharts()},280)}});
  document.body.addEventListener("htmx:afterRequest",ev=>{const form=qs("#rpm-progress-form");if(!form)return;if(ev.detail&&ev.detail.elt===form){form.dataset.saving="0";if(form.dataset.pending==="1"){form.dataset.pending="0";requestAutoSave(form,120)}}});
  document.body.addEventListener("htmx:afterSettle",()=>{configureHtmx();bindProgressForm();bindChapterTabs();bindConfirmForms();bindImagePreview();applyActiveChapter();syncMindmapDetails();initCharts();initMindmap();resizeCharts()})
})();
