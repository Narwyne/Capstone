<!-- includes/privacy_modal.php — Privacy Policy modal (include before </body>) -->
<div id="privacyModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
    <div class="text-white px-5 py-4 rounded-t-2xl flex justify-between items-center shrink-0" style="background:linear-gradient(90deg,#152875,#b91c1c);">
      <h3 class="font-bold text-lg">🔒 Privacy Policy</h3>
      <button type="button" onclick="closePrivacy()" class="text-white hover:text-red-200 text-2xl leading-none">&times;</button>
    </div>
    <div class="p-5 overflow-y-auto space-y-5 text-sm text-gray-600 leading-relaxed">
      <?php include __DIR__ . '/privacy_content.php'; ?>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 flex gap-2 justify-end shrink-0">
      <button type="button" onclick="closePrivacy()" class="px-4 py-2 rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-medium">Close</button>
      <button type="button" onclick="agreePrivacy()" class="px-4 py-2 rounded-xl text-white text-sm font-semibold hover:opacity-90" style="background:linear-gradient(90deg,#152875,#b91c1c);">I Agree</button>
    </div>
  </div>
</div>
<script>
function openPrivacy(){const m=document.getElementById('privacyModal');m.classList.remove('hidden');m.classList.add('flex');document.body.style.overflow='hidden';}
function closePrivacy(){const m=document.getElementById('privacyModal');m.classList.add('hidden');m.classList.remove('flex');document.body.style.overflow='';}
function agreePrivacy(){const c=document.querySelector('input[name="terms"]');if(c)c.checked=true;closePrivacy();}
document.getElementById('privacyModal').addEventListener('click',e=>{if(e.target===e.currentTarget)closePrivacy();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closePrivacy();});
</script>
