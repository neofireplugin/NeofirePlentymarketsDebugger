const { Application } = Shopware;

import PlentyService from '../../src/core/service/api/plenty.service';

Application.addServiceProvider('PlentyService', (container) => {
    const initContainer = Application.getContainer('init');

    return new PlentyService(initContainer.httpClient, container.loginService);
});
