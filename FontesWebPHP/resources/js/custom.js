(function ($) {
    window.MASK_CNPJ_CPF = {
        onKeyPress: function (cpf, ev, el, op) {
            var masks = ["000.000.000-000", "00.000.000/0000-00"];
            $(".mask-cnpj_cpf").mask(cpf.length > 14 ? masks[1] : masks[0], op);
        },
    };

    window.SPMaskBehavior = function (val) {
        return val.replace(/\D/g, "").length === 11
            ? "(00) 00000-0000"
            : "(00) 0000-00009";
    };

    window.spOptions = {
        onKeyPress: function (val, e, field, options) {
            field.mask(SPMaskBehavior.apply({}, arguments), options);
        },
    };

    $(document).on("livewire:init", function () {
        Livewire.on("eventOpenModal", (event) => {
            $(event.modalId).modal("open");
        });

        Livewire.on("eventCloseModal", (event) => {
            $(event.modalId).modal("close");
        });

        Livewire.on("eventCuteToast", (event) => {
            let code = event.code;
            let msg = event.msg;

            switch (code) {
                case 200:
                    cuteToast({ type: "success", message: msg, timer: 5000 });
                    break;

                case 100:
                case 300:
                    cuteToast({ type: "warning", message: msg, timer: 5000 });
                    break;

                case 400:
                case 404:
                case 403:
                    cuteToast({ type: "info", message: msg, timer: 5000 });
                    break;

                case 500:
                case 23000:
                    cuteToast({ type: "error", message: msg, timer: 5000 });
                    break;
            }
        });
    });
})(jQuery);
