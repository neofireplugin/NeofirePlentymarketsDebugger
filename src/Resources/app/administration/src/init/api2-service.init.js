const { Application } = Shopware;

import ConnectService from '../../src/core/service/api/connect.service';

Application.addServiceProvider('ConnectService', (container) => {
    const initContainer = Application.getContainer('init');

    return new ConnectService(initContainer.httpClient, container.loginService);
});
