// Dashboard live refresh (only runs on pages that have the tables).
(function () {
  const base = window.ATT_BASE || '';
  const presentBody = document.querySelector('#present-tbl tbody');
  const feedBody = document.querySelector('#feed-tbl tbody');
  if (!presentBody && !feedBody) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, c =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  async function refresh() {
    try {
      const r = await fetch(base + '/api/live.php', { credentials: 'same-origin' });
      if (!r.ok || r.redirected) throw new Error('Refresh failed');
      const d = await r.json();
      document.getElementById('live-feedback')?.remove();

      if (presentBody) {
        presentBody.innerHTML = d.present.length
          ? d.present.map(p => `<tr><td>${esc(p.emp_no)}</td><td>${esc(p.name)}</td>
              <td>${esc(p.check_in)}</td><td>${esc(p.check_out || p.checkout_state)}</td>
              <td><span class="status-label status-${['late','half'].includes(p.status) ? 'warning' : p.status==='present' ? 'success' : 'neutral'}"><span class="status-symbol" aria-hidden="true"></span>${esc(p.status === 'half' ? 'Half day' : p.status.charAt(0).toUpperCase()+p.status.slice(1))}</span></td></tr>`).join('')
          : '<tr><td colspan="5" class="muted">Nobody checked in yet today.</td></tr>';
      }
      if (feedBody) {
        feedBody.innerHTML = d.feed.length
          ? d.feed.map(f => `<tr><td>${esc(f.time)}</td><td>${esc(f.emp_no)}</td><td>${esc(f.name)}</td></tr>`).join('')
          : '<tr><td colspan="3" class="muted">No scans yet.</td></tr>';
      }
    } catch (e) { if(!document.getElementById('live-feedback')) { const notice=document.createElement('p');notice.id='live-feedback';notice.className='alert err';notice.setAttribute('role','status');notice.textContent='Live updates are unavailable. Check your connection or sign in again. Retrying automatically.'; (presentBody||feedBody).closest('.panel').prepend(notice); } }
  }

  refresh();
  setInterval(refresh, 5000);
})();

// Shared Fluent workspace interactions.
(() => {
 const main=document.querySelector('.content');if(!main)return;
 const title=main.querySelector(':scope > h1');
 if(title){const header=document.createElement('div');header.className='page-heading';title.before(header);header.append(title);const description=main.querySelector(':scope > .page-description');if(description)header.append(description);
 const actions=main.querySelector(':scope > p > .btn');if(actions){const actionBox=document.createElement('div');actionBox.className='page-actions';header.append(actionBox);actionBox.append(actions);}}
 const toolbar=main.querySelector(':scope > .filters');if(toolbar)toolbar.classList.add('command-bar');
 document.querySelectorAll('.alert.ok').forEach(el=>el.setAttribute('role','status'));
 document.querySelectorAll('.alert.err').forEach(el=>el.setAttribute('role','alert'));
 // Local lists sort their visible rows; paginated report headings update server sorting.
 document.querySelectorAll('.tbl').forEach(table=>{
  if(table.id==='present-tbl'||table.id==='feed-tbl')return;
  const headers=table.querySelectorAll('thead th');
  headers.forEach((th,index)=>{
   const label=th.textContent.trim();if(['Actions','Action','Needs attention'].includes(label))return;
   const isReport=location.pathname.endsWith('/reports.php');
   const reportSort={'Date':'date','Name':'employee','Worked':'worked','Calculated OT':'overtime','OT':'overtime'};
   if(isReport&&!reportSort[label])return;
   const button=document.createElement('button');button.className='column-sort';button.type='button';button.textContent=label+' ↕';th.textContent='';th.append(button);
   button.addEventListener('click',()=>{const asc=th.getAttribute('aria-sort')!=='ascending';
    if(isReport){const url=new URL(location.href);url.searchParams.set('sort',reportSort[label]);url.searchParams.set('direction',asc?'asc':'desc');url.searchParams.delete('page');location.assign(url);return;}
    headers.forEach(h=>h.removeAttribute('aria-sort'));th.setAttribute('aria-sort',asc?'ascending':'descending');
    const body=table.tBodies[0];const rows=Array.from(body.rows).filter(r=>r.cells.length===headers.length);
    rows.sort((a,b)=>a.cells[index].textContent.trim().localeCompare(b.cells[index].textContent.trim(),undefined,{numeric:true})*(asc?1:-1));rows.forEach(r=>body.append(r));
   });
   if(isReport){const params=new URLSearchParams(location.search);if((params.get('sort')||'date')===reportSort[label])th.setAttribute('aria-sort',params.get('direction')==='asc'?'ascending':'descending');}
  });
 });
 let dialog,frame,originLink;
 document.querySelectorAll('[data-edit-panel]').forEach(link=>link.addEventListener('click',event=>{
  if(event.ctrlKey||event.metaKey||event.shiftKey)return;event.preventDefault();originLink=link;
  if(!dialog){dialog=document.createElement('dialog');dialog.className='edit-drawer';dialog.setAttribute('aria-label','Edit employee');dialog.innerHTML='<div class="drawer-heading"><strong>Edit employee</strong><button type="button" class="btn ghost" aria-label="Close employee editor">Close</button></div><p class="drawer-loading" role="status">Loading employee details…</p><iframe title="Employee editing form"></iframe>';document.body.append(dialog);frame=dialog.querySelector('iframe');dialog.querySelector('button').onclick=()=>{if(!frame.contentWindow.attendanceHasUnsavedChanges?.()||confirm('Discard unsaved changes?'))dialog.close();};dialog.addEventListener('cancel',event=>{if(frame.contentWindow.attendanceHasUnsavedChanges?.()&&!confirm('Discard unsaved changes?'))event.preventDefault();});dialog.addEventListener('close',()=>{frame.src='about:blank';originLink?.focus();});frame.addEventListener('load',()=>dialog.querySelector('.drawer-loading').hidden=true);}
  dialog.querySelector('.drawer-loading').hidden=false;const url=new URL(link.href);url.searchParams.set('panel','1');frame.src=url.href;dialog.showModal();
 }));
 window.addEventListener('message',event=>{if(event.origin!==location.origin||event.source!==frame?.contentWindow)return;if(event.data?.type==='employee-saved'){dialog.close();location.reload();}if(event.data?.type==='employee-cancel' && (!frame.contentWindow.attendanceHasUnsavedChanges?.()||confirm('Discard unsaved changes?')))dialog.close();});
 document.addEventListener('click',event=>{document.querySelectorAll('.profile-menu[open],.row-menu[open]').forEach(menu=>{if(!menu.contains(event.target))menu.open=false;});});
})();

// Shared disclosure pattern: keep list pages focused on their records.
(() => {
 const main=document.querySelector('.content');if(!main||document.body.classList.contains('panel-page'))return;
 const page=location.pathname.split('/').pop();
 const actions={'corrections.php':['New correction','New correction request'],'leave.php':['Add leave','Add a leave record'],'accounts.php':['Add user','Create a user account']};
 if(actions[page]){
  const form=main.querySelector(':scope > form[method="post"]');
  if(form){const disclosure=document.createElement('details');disclosure.className='action-disclosure';const summary=document.createElement('summary');summary.textContent=actions[page][0];disclosure.append(summary);form.before(disclosure);disclosure.append(form);
   if(main.querySelector('.alert.err')||new URLSearchParams(location.search).has('emp'))disclosure.open=true;
   form.setAttribute('aria-label',actions[page][1]);
  }
 }
 // Give existing record tables one consistent scroll container.
 main.querySelectorAll('.tbl').forEach(table=>{if(table.closest('.table-scroll'))return;const wrap=document.createElement('div');wrap.className='table-scroll';table.before(wrap);wrap.append(table);});
})();

// Local portrait preview; the server validates and re-encodes every upload.
document.querySelectorAll('[data-photo-input]').forEach(input=>{
  const preview=input.form.querySelector('[data-photo-preview]');let url;
  input.addEventListener('change',()=>{
    if(url)URL.revokeObjectURL(url);
    const file=input.files[0];preview.hidden=true;input.setCustomValidity('');
    if(!file)return;
    if(!['image/jpeg','image/png'].includes(file.type)||file.size>5*1024*1024){input.setCustomValidity('Choose a JPG or PNG photo up to 5 MB.');input.reportValidity();return;}
    url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;
  });
});

// Keep overtime validation beside the decision being made.
document.querySelectorAll('[data-overtime-review]').forEach(form=>{
  const feedback=form.querySelector('.overtime-feedback');
  form.noValidate=true;
  form.addEventListener('submit',event=>{
    feedback.hidden=true;
    const reason=form.elements.reason;
    if(reason.value.trim().length<5 || reason.value.length>1000){
      event.preventDefault();feedback.textContent='Enter a review reason of 5–1000 characters, then choose Approve or Reject.';feedback.hidden=false;reason.focus();return;
    }
    if(event.submitter?.value!=='rejected' && !form.elements.minutes.checkValidity()){
      event.preventDefault();feedback.textContent='Enter approved minutes between 0 and '+form.elements.minutes.max+'.';feedback.hidden=false;form.elements.minutes.focus();
    }
  });
});

// Protect edited forms and keep the clicked action in the submitted data.
(() => {
 const dirty=new Set();let leaving=false;
 window.attendanceHasUnsavedChanges=()=>dirty.size>0 && !leaving;
 document.querySelectorAll('form[method="post"]').forEach(form=>{
  form.addEventListener('input',()=>dirty.add(form));form.addEventListener('change',()=>dirty.add(form));
 });
 document.addEventListener('submit',event=>{
  const form=event.target;if(form.method.toLowerCase()!=='post'||event.defaultPrevented||form.hasAttribute('data-download-form'))return;
  if(form.dataset.submitting){event.preventDefault();return;}
  const button=event.submitter;
  if(button?.name){const field=document.createElement('input');field.type='hidden';field.name=button.name;field.value=button.value;field.dataset.submitAction='1';form.append(field);}
  form.dataset.submitting='1';leaving=true;dirty.delete(form);form.setAttribute('aria-busy','true');
  form.querySelectorAll('button[type="submit"],button:not([type]),input[type="submit"]').forEach(el=>{el.disabled=true;el.dataset.submitDisabled='1';});
 });
 window.addEventListener('beforeunload',event=>{if(window.attendanceHasUnsavedChanges()){event.preventDefault();event.returnValue='';}});
 window.addEventListener('pageshow',()=>{leaving=false;document.querySelectorAll('[data-submitting]').forEach(form=>{delete form.dataset.submitting;form.removeAttribute('aria-busy');});document.querySelectorAll('[data-submit-disabled]').forEach(el=>{el.disabled=false;delete el.dataset.submitDisabled;});document.querySelectorAll('[data-submit-action]').forEach(el=>el.remove());});
})();
// Read-only attendance detail drawer, with a normal link fallback.
document.querySelectorAll('[data-attendance-panel]').forEach(link=>link.addEventListener('click',event=>{
 if(event.ctrlKey||event.metaKey||event.shiftKey)return;event.preventDefault();
 const dialog=document.createElement('dialog');dialog.className='edit-drawer';dialog.setAttribute('aria-label','Attendance details');
 dialog.innerHTML='<div class="drawer-heading"><strong>Attendance details</strong><button type="button" class="btn ghost">Close</button></div><iframe title="Attendance details"></iframe>';
 const url=new URL(link.href);url.searchParams.set('panel','1');dialog.querySelector('iframe').src=url;document.body.append(dialog);
 dialog.querySelector('button').onclick=()=>dialog.close();dialog.addEventListener('close',()=>{dialog.remove();link.focus();});dialog.showModal();
}));

document.querySelectorAll('[data-attendance-row]').forEach(row=>row.addEventListener('click',event=>{
 if(event.target.closest('a,button,input,select,summary')||window.getSelection().toString())return;
 row.querySelector('[data-attendance-panel]').click();
}));

(() => {
 const form=document.querySelector('.employee-create');if(!form)return;
 const name=form.elements.name,photo=form.elements.photo,initials=form.querySelector('[data-create-initials]'),remove=form.querySelector('[data-clear-photo]');
 const update=()=>{const parts=name.value.trim().split(/\s+/).filter(Boolean);initials.textContent=parts.length?(Array.from(parts[0])[0]+(parts.length>1?Array.from(parts[parts.length-1])[0]:'')).toLocaleUpperCase():'?';};
 name.addEventListener('input',update);update();
 photo.addEventListener('change',()=>{remove.hidden=!photo.files.length;});
 remove.addEventListener('click',()=>{photo.value='';photo.dispatchEvent(new Event('change',{bubbles:true}));});
})();

// Reveal invalid optional fields even when their section was collapsed.
document.addEventListener('invalid',event=>{let node=event.target.parentElement;while(node){if(node.tagName==='DETAILS')node.open=true;node=node.parentElement;}},true);
