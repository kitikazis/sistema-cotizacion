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

        init() {
            this.agregarItem();
        },

        itemVacio() {
            return {
                codigo: '',
                marca: '',
                descripcion: '',
                cantidad: 1,
                precio: 0,
                licencia_so: 0,
                delivery: 0,
                embalaje: 0,
                envio: 0,
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
