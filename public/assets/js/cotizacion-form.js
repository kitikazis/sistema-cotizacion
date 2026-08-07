/**
 * Componente Alpine del formulario de cotizacion.
 *
 * OJO: el calculo de aqui es SOLO PREVISUALIZACION para que el vendedor vea
 * los numeros mientras escribe. La cifra que se guarda la recalcula siempre
 * PricingCalculator en el servidor. Si tocas la formula, hay que tocarla en
 * helpers/PricingCalculator.php (fuente de verdad) y reflejarla aqui.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('cotizacionForm', (config = {}) => ({
        // Tasas espejo de PricingCalculator
        TASA_IR: 0.015,
        TASA_IGV: 0.18,
        TASA_DETRACCION: 0.12,
        TASA_RETENCION: 0.03,

        ganancias: config.ganancias || [0.10, 0.12, 0.14, 0.20, 0.22, 0.24],
        moneda: config.moneda || 'PEN',
        formaPago: config.formaPago || 'contado',
        items: [],

        // ---- Cliente y validacion ----
        clientes: config.clientes || [],
        cliente: config.cliente || { empresa: '', ruc: '', direccion: '' },
        // Un campo solo muestra su error despues de visitarlo: marcar en rojo
        // algo que el usuario todavia no toco es hostil.
        tocado: { empresa: false, ruc: false },
        enviando: false,

        /**
         * Al escribir un nombre que coincide con un cliente ya registrado,
         * completa RUC y direccion. Solo rellena lo que este vacio, para no
         * pisar una correccion hecha a mano.
         */
        autocompletarCliente() {
            const nombre = (this.cliente.empresa || '').trim().toLowerCase();
            const hallado = this.clientes.find((c) => c.nombre.toLowerCase() === nombre);

            if (!hallado) {
                return;
            }

            if (!this.cliente.ruc) {
                this.cliente.ruc = hallado.ruc;
            }
            if (!this.cliente.direccion) {
                this.cliente.direccion = hallado.direccion;
            }
        },

        get errorEmpresa() {
            if (!this.tocado.empresa) {
                return '';
            }

            return (this.cliente.empresa || '').trim() === ''
                ? 'Escribe la razón social del cliente.'
                : '';
        },

        get errorRuc() {
            const ruc = (this.cliente.ruc || '').trim();

            if (!this.tocado.ruc || ruc === '') {
                return '';   // el RUC es opcional
            }

            if (!/^\d+$/.test(ruc)) {
                return 'El RUC solo lleva números.';
            }

            if (ruc.length !== 11) {
                return `El RUC tiene 11 dígitos; escribiste ${ruc.length}.`;
            }

            return '';
        },

        /** ¿Hay al menos un item con descripcion? */
        get errorItems() {
            return this.items.some((i) => (i.descripcion || '').trim() !== '')
                ? ''
                : 'Agrega al menos un ítem con descripción.';
        },

        get puedeGuardar() {
            return !this.enviando
                && (this.cliente.empresa || '').trim() !== ''
                && this.errorRuc === ''
                && this.errorItems === '';
        },

        /** Marca todo como visitado para que los errores salgan a la vista. */
        alEnviar(ev) {
            this.tocado.empresa = true;
            this.tocado.ruc = true;

            if (!this.puedeGuardar) {
                ev.preventDefault();
                ev.stopPropagation();
                return;
            }

            this.enviando = true;
        },

        init() {
            // Al editar llegan los items ya guardados; al crear, una fila vacia.
            if (Array.isArray(config.items) && config.items.length > 0) {
                this.items = config.items.map((item) => ({ ...this.itemVacio(), ...item }));
            } else {
                this.agregarItem();
            }
        },

        itemVacio() {
            // Los costos arrancan vacios, no en cero: una fila nueva llena de
            // ceros se lee como ruido y obliga a borrarlos para escribir. El
            // calculo trata la cadena vacia como 0, asi que no cambia nada.
            return {
                codigo: '',
                marca: '',
                descripcion: '',
                cantidad: 1,
                precio: '',
                licencia_so: '',
                delivery: '',
                embalaje: '',
                envio: '',
                aplica_detraccion: false,
                aplica_retencion: false,
                porcentaje_ganancia: 0.10,
            };
        },

        agregarItem() {
            this.items.push(this.itemVacio());
        },

        duplicarItem(indice) {
            this.items.splice(indice + 1, 0, { ...this.items[indice] });
        },

        quitarItem(indice) {
            this.items.splice(indice, 1);
            if (this.items.length === 0) {
                this.agregarItem();
            }
        },

        /** Convierte a numero tolerando vacios y comas. */
        n(valor) {
            if (typeof valor === 'string') {
                valor = valor.replace(/\s/g, '').replace(',', '.');
            }
            const numero = parseFloat(valor);
            return Number.isFinite(numero) ? numero : 0;
        },

        /** Espejo exacto de PricingCalculator::calcularItem. */
        calcular(item) {
            const precio = this.n(item.precio);
            const cantidad = this.n(item.cantidad);

            const ir = precio * this.TASA_IR;
            const igv = precio * this.TASA_IGV;
            const detraccion = item.aplica_detraccion ? precio * this.TASA_DETRACCION : 0;
            const ganancia = precio * this.n(item.porcentaje_ganancia);

            const subtotal = precio
                + ir
                + igv
                + detraccion
                + this.n(item.licencia_so)
                + this.n(item.delivery)
                + this.n(item.embalaje)
                + this.n(item.envio)
                + ganancia;

            const retencion = item.aplica_retencion ? subtotal * this.TASA_RETENCION : 0;
            const totalUnitario = subtotal + retencion;

            return {
                ir,
                igv,
                detraccion,
                ganancia,
                subtotal,
                retencion,
                totalUnitario,
                totalLinea: totalUnitario * cantidad,
                precioCliente: totalUnitario / (1 + this.TASA_IGV),
            };
        },

        /** Total interno de la cotizacion. */
        get totalGeneral() {
            return this.items.reduce((suma, item) => suma + this.calcular(item).totalLinea, 0);
        },

        /** Base imponible que ve el cliente. */
        get clienteSubtotal() {
            return this.items.reduce(
                (suma, item) => suma + this.calcular(item).precioCliente * this.n(item.cantidad),
                0
            );
        },

        get clienteIgv() {
            return this.clienteSubtotal * this.TASA_IGV;
        },

        get clienteTotal() {
            return this.clienteSubtotal + this.clienteIgv;
        },

        get simbolo() {
            return this.moneda === 'USD' ? 'US$' : 'S/';
        },

        /** Formato de dinero para pantalla. */
        f(valor, decimales = 2) {
            return this.n(valor).toLocaleString('es-PE', {
                minimumFractionDigits: decimales,
                maximumFractionDigits: decimales,
            });
        },

        /** Etiqueta legible del % de ganancia. */
        pct(valor) {
            return Math.round(this.n(valor) * 100) + '%';
        },
    }));
});
