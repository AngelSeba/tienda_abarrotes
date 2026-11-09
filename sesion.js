(()=>{
 const script=document.querySelector('script[data-sesion="1"]'); if(!script)return;
 const endpoint=new URL(script.dataset.endpoint,document.baseURI);let limite=Date.now()+Number(script.dataset.restante)*1000;
 let consultando=false,banner=null,texto=null,boton=null,reintento=0;
 function aviso(mensaje){
  if(!banner){
   banner=document.createElement('aside');banner.className='aviso-sesion';banner.setAttribute('role','status');banner.setAttribute('aria-live','polite');
   texto=document.createElement('p');boton=document.createElement('button');boton.type='button';boton.textContent='Continuar sesión';boton.addEventListener('click',()=>consultar(true));
   banner.append(texto,boton);document.body.appendChild(banner);
  }
  texto.textContent=mensaje;
 }
 async function consultar(renovar){
  if(consultando)return;consultando=true;if(boton)boton.disabled=true;
  try{
   const opciones={method:renovar?'POST':'GET',credentials:'same-origin',cache:'no-store'};
   if(renovar){opciones.headers={'Content-Type':'application/x-www-form-urlencoded'};opciones.body=new URLSearchParams({csrf_general:script.dataset.csrf}).toString();}
   const respuesta=await fetch(endpoint,opciones);const estado=await respuesta.json();
   if(estado.vigente===false){window.location.assign(estado.inicio || new URL(script.dataset.inicio,document.baseURI).href);return;}
   if(!respuesta.ok || estado.vigente!==true)throw Error('No disponible');
   limite=Date.now()+Number(estado.restante)*1000;reintento=0;
   if(banner && Number(estado.restante)>120){banner.remove();banner=null;texto=null;boton=null;}
  }catch(error){
   aviso('No se pudo comprobar la sesión. Revisa la conexión antes de guardar. Puedes intentar continuar de nuevo.');reintento=Date.now()+30000;
  }finally{consultando=false;if(boton)boton.disabled=false;}
 }
 function revisar(){
  const segundos=Math.ceil((limite-Date.now())/1000);
  if(segundos<=0){if(Date.now()>=reintento)consultar(false);return;}
  if(segundos<=120)aviso('Tu sesión vencerá en aproximadamente '+Math.ceil(segundos/60)+' minuto(s). Guarda tus cambios o pulsa Continuar sesión.');
 }
 setInterval(revisar,1000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)revisar();});revisar();
})();
