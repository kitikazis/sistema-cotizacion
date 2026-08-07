// Comprueba la validacion y el autocompletado del formulario fuera del navegador.
let comp = null;
global.document = { addEventListener: (e, fn) => { if (e === 'alpine:init') fn(); } };
global.Alpine = { data: (_n, factory) => { comp = factory({
    clientes: [
        { nombre: 'Claro SAC', ruc: '20100017491', direccion: 'Av. Nicolas Arriola 480' },
        { nombre: 'Movistar',  ruc: '20100017492', direccion: 'Av. Arequipa 1155' },
    ],
    cliente: { empresa: '', ruc: '', direccion: '' },
}); } };

require(__dirname + '/../public/assets/js/cotizacion-form.js');

let fallos = 0;
function chk(etq, ok, extra = '') {
    if (!ok) fallos++;
    console.log(`  ${ok ? 'OK   ' : 'FALLA'} ${etq}${extra ? ' -> ' + extra : ''}`);
}

console.log('Autocompletado de cliente');
comp.cliente.empresa = 'Claro SAC';
comp.autocompletarCliente();
chk('rellena el RUC', comp.cliente.ruc === '20100017491', comp.cliente.ruc);
chk('rellena la dirección', comp.cliente.direccion === 'Av. Nicolas Arriola 480');

console.log('\nNo pisa lo escrito a mano');
comp.cliente = { empresa: 'Movistar', ruc: '99999999999', direccion: '' };
comp.autocompletarCliente();
chk('conserva el RUC tecleado', comp.cliente.ruc === '99999999999', comp.cliente.ruc);
chk('sí rellena la dirección vacía', comp.cliente.direccion === 'Av. Arequipa 1155');

console.log('\nCliente desconocido');
comp.cliente = { empresa: 'Empresa Nueva SAC', ruc: '', direccion: '' };
comp.autocompletarCliente();
chk('no inventa datos', comp.cliente.ruc === '' && comp.cliente.direccion === '');

console.log('\nValidación del RUC');
comp.tocado.ruc = true;
comp.cliente.ruc = '';
chk('vacío es válido (es opcional)', comp.errorRuc === '');
comp.cliente.ruc = '2010001749A';
chk('rechaza letras', comp.errorRuc.includes('números'), comp.errorRuc);
comp.cliente.ruc = '201000';
chk('avisa cuántos dígitos faltan', comp.errorRuc.includes('6'), comp.errorRuc);
comp.cliente.ruc = '20100017491';
chk('acepta 11 dígitos', comp.errorRuc === '');

console.log('\nValidación de empresa');
comp.tocado.empresa = false;
comp.cliente.empresa = '';
chk('no marca error sin haberlo tocado', comp.errorEmpresa === '');
comp.tocado.empresa = true;
chk('marca error tras salir del campo', comp.errorEmpresa !== '');

console.log('\nGuardar bloqueado hasta que esté correcto');
comp.items = [];
comp.cliente.empresa = 'Claro SAC';
chk('bloquea sin ítems', comp.puedeGuardar === false, comp.errorItems);
comp.items = [{ descripcion: 'Router', cantidad: 1, precio: 100 }];
chk('habilita con un ítem válido', comp.puedeGuardar === true);
comp.cliente.ruc = '123';
chk('vuelve a bloquear con RUC malo', comp.puedeGuardar === false);

console.log('\n' + '='.repeat(50));
console.log(fallos === 0 ? 'Validación y autocompletado correctos.' : `${fallos} fallos.`);
process.exit(fallos === 0 ? 0 : 1);
