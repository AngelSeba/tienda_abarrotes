(() => {
  const etapas = [...document.querySelectorAll('[data-etapa]')];
  const form = document.getElementById('instalar');
  if (!form || etapas.length !== 3) return;
  let actual = 0;
  function mostrar(n) {
    actual = n;
    etapas.forEach((e, i) => e.hidden = i !== n);
    document.querySelectorAll('.pasos li').forEach((e,i) => { e.classList.toggle('activo',i===n); if(i===n)e.setAttribute('aria-current','step'); else e.removeAttribute('aria-current'); });
  }
  function validar(etapa) {
    for (const el of etapas[etapa].querySelectorAll('input')) if (!el.reportValidity()) return false;
    return true;
  }
  document.querySelectorAll('[data-siguiente]').forEach(b => b.addEventListener('click',()=>mostrar(actual+1)));
  document.querySelectorAll('[data-anterior]').forEach(b => b.addEventListener('click',()=>mostrar(actual-1)));
  const comprobar = document.getElementById('comprobar');
  comprobar.addEventListener('click',async()=>{
    if(!validar(1))return;
    comprobar.disabled=true;
    const salida=document.getElementById('resultado'); salida.hidden=false; salida.textContent='Comprobando conexión…';
    const datos=new FormData(form); datos.set('accion','comprobar');
    try {
      const respuesta=await fetch('./',{method:'POST',body:datos,credentials:'same-origin'});
      const resultado=await respuesta.json(); salida.textContent=resultado.mensaje;
      if(resultado.ok)mostrar(2);
    } catch(e) { salida.textContent='No se pudo comprobar la conexión. Recarga la página e inténtalo de nuevo.'; }
    finally { comprobar.disabled=false; }
  });
  // Validación propia permite enfocar campos de pasos ocultos correctamente.
  form.noValidate=true;
  form.addEventListener('submit',e=>{
    for(const n of [1,2]) {
      mostrar(n);
      if(!validar(n)){e.preventDefault();return;}
    }
    const pass=form.elements.password, confirm=form.elements.confirmacion;
    confirm.setCustomValidity(pass.value===confirm.value?'':'Las contraseñas no coinciden.');
    if(!confirm.reportValidity()){e.preventDefault();return;}
    if(new TextEncoder().encode(pass.value).length>72){e.preventDefault();pass.setCustomValidity('La contraseña supera 72 bytes. Usa una frase más corta.');pass.reportValidity();return;}
    document.getElementById('instalar-boton').disabled=true;
    document.getElementById('espera').hidden=false;
  });
  form.elements.confirmacion.addEventListener('input',()=>form.elements.confirmacion.setCustomValidity(''));
  form.elements.password.addEventListener('input',()=>{form.elements.password.setCustomValidity('');form.elements.confirmacion.setCustomValidity('');});
  mostrar(0);
})();
