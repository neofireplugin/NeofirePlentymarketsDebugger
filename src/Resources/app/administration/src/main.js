import './module/neofire-plentymarketsconnector-module';
import './module/component/neofire-plentymarkets-connector-component/neofire-plentymarketsconnector-content';
import './init/api-service.init';
import './init/api2-service.init';

const {Component} = Shopware;

Component.register('plentyplugin', {
    template: '<template><div style="text-align:center;" ><p id="plentylink">LINK</p></div></template>'
});
Component.register('plentypluginlink', {
    template: '',
    render() {

        document.getElementById('salesChannelSelect').style.display = "none";

        if(document.getElementById('NeofirePlentymarketsDebugger.config.securekey').value == ''){

            var fields = document.getElementById('NeofirePlentymarketsDebugger.config.securekey').parentNode.parentNode;
            fields.__vue__.$children[0].$el.__vue__.$data.currentValue = this.generatePassword();

        }
        document.getElementById('plentylink').textContent = window.location.protocol + "//" + window.location.hostname + '/admin/plentymarkets?key=' + document.getElementById("NeofirePlentymarketsDebugger.config.securekey").value;
        const field = document.getElementById('NeofirePlentymarketsDebugger.config.securekey');
        if (field) {
            field.addEventListener("change", this.createlink());
        }
    },
    methods: {
        createlink() {
            document.getElementById('plentylink').textContent = window.location.protocol + "//" + window.location.hostname + '/admin/plentymarkets?key=' + document.getElementById("NeofirePlentymarketsDebugger.config.securekey").value;
        },
        generatePassword() {
            var length = 8,
                charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",
                retVal = "";
            for (var i = 0, n = charset.length; i < length; ++i) {
                retVal += charset.charAt(Math.floor(Math.random() * n));
            }
            return retVal;
        }
    }
});


