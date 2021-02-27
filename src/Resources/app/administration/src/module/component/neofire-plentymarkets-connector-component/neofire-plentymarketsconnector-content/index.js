import { Component } from 'src/core/shopware';
import template from './neofire_admin.html.twig';

Component.register('NeofirePlentymarketsDebugger', {
    template,

    inject: ['PlentyService'],

    created() {
        this.createdComponent();
    },

    data() {
        return {
            apiUserData: false
        };
    },
    
    methods: {
        createdComponent() {
            this.PlentyService.loginToPlentymarektsConnector().then(response => {
                this.apiUserData = response.apidata;
            });
        },

        openNewTab() {
            window.open(this.apiUserData);
        }


    }

});