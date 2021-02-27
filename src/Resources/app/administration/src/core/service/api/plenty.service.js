import ApiService from 'src/core/service/api.service';

class PlentyService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'admin/plentymarkets/getaccess') {
        super(httpClient, loginService, apiEndpoint);
    }
    loginToPlentymarektsConnector() {
        const apiRoute = `/admin/plentymarkets/getaccess`;
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

export default PlentyService;
