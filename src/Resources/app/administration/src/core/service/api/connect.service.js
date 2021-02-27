import ApiService from 'src/core/service/api.service';

class ConnectService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'admin/plentymarkets/getaccess') {
        super(httpClient, loginService, apiEndpoint);
    }
    loginToPlentymarektsConnector(plentymarketsuser,plentymarketspass,plentymarketsurl) {
        const apiRoute = '/admin/plentymarkets/checkaccess?plentymarketsuser='+plentymarketsuser+'&plentymarketspass='+plentymarketspass+'&plentymarketsurl='+plentymarketsurl;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default ConnectService;
