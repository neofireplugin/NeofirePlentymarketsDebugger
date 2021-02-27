import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('neofire-plentymarketsconnector', {

    type: 'plugin',
    name: 'NeofirePlentymarketsDebugger',
    title: 'NeofirePlentymarketsDebugger.general.title',
    description: 'NeofirePlentymarketsDebugger.general.description',
    color: '#d0d9df',
    icon: 'small-arrow-small-reoder',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    settingsItem: {
        group: 'plugins',
        to: 'neofire.plentymarketsconnector.overview',
        icon: 'small-arrow-small-reoder'
    },

    routes: {
        overview: {
            component: 'NeofirePlentymarketsDebugger',
            path: 'overview'
        }
    }
});
