<?php declare(strict_types=1);

namespace NeofirePlentymarketsDebugger\Administration\Controller;

use Doctrine\DBAL\Connection;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Shopware\Core\System\SystemConfig\SystemConfigService;

use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsBase;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsConnect;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsOrders;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsStock;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsCategories;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsProduct;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsData;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsRestarts;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsDeliveryTime;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsManufacturers;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsUnits;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsTax;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsAttributes;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsVariationAttributes;
use NeofirePlentymarketsConnector\Administration\Service\PlentymarketsFields;

class PlentymarketsController extends AbstractController
{
    private $systemConfigService;
    private $plentymarketsBase;
    private $finder;
    private $plentymarketsOrders;
    private $plentymarketsStock;
    private $plentymarketsCategories;
    private $plentymarketsProduct;
    private $plentymarketsData;
    private $plentymarketsRestarts;
    private $plentymarketsDeliveryTime;
    private $plentymarketsManufacturers;
    private $plentymarketsUnits;
    private $plentymarketsTax;
    private $plentymarketsAttributes;
    private $plentymarketsVariationAttributes;
    private $plentymarketsFields;
    private $dbal;
    private $context;
    private $request;

    private $iframeSuccessValue = "SUCCESS";

    public function __construct(
        SystemConfigService $systemConfigService,
        PlentymarketsBase $plentymarketsBase,
        TemplateFinder $finder,
        PlentymarketsOrders $plentymarketsOrders,
        PlentymarketsStock $plentymarketsStock,
        PlentymarketsCategories $plentymarketsCategories,
        PlentymarketsProduct $plentymarketsProduct,
        PlentymarketsData $plentymarketsData,
        PlentymarketsRestarts $plentymarketsRestarts,
        PlentymarketsDeliveryTime $plentymarketsDeliveryTime,
        PlentymarketsManufacturers $plentymarketsManufacturers,
        PlentymarketsUnits $plentymarketsUnits,
        PlentymarketsTax $plentymarketsTax,
        PlentymarketsAttributes $plentymarketsAttributes,
        PlentymarketsVariationAttributes $plentymarketsVariationAttributes,
        PlentymarketsFields $plentymarketsFields,
        Connection $dbal
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->plentymarketsBase = $plentymarketsBase;
        $this->finder = $finder;
        $this->plentymarketsOrders = $plentymarketsOrders;
        $this->plentymarketsStock = $plentymarketsStock;
        $this->plentymarketsCategories = $plentymarketsCategories;
        $this->plentymarketsProduct = $plentymarketsProduct;
        $this->plentymarketsData = $plentymarketsData;
        $this->plentymarketsRestarts = $plentymarketsRestarts;
        $this->plentymarketsDeliveryTime = $plentymarketsDeliveryTime;
        $this->plentymarketsManufacturers = $plentymarketsManufacturers;
        $this->plentymarketsUnits = $plentymarketsUnits;
        $this->plentymarketsTax = $plentymarketsTax;
        $this->plentymarketsAttributes = $plentymarketsAttributes;
        $this->plentymarketsVariationAttributes = $plentymarketsVariationAttributes;
        $this->plentymarketsFields = $plentymarketsFields;
        $this->dbal = $dbal;
    }



    //Hier wird die Iframe URL sowie die Session übertragen
    /**
     * @RouteScope(scopes={"administration"})
     * @Route("/api/v{version}/admin/plentymarkets/getaccess", name="api.admin.plentymarkets.getaccess", methods={"GET"})
     */
    public function getaccess(Request $request):JsonResponse
    {
        session_cache_limiter('');
        session_name('neofire_sid');
        session_start();

        $_SESSION['NeofireiFrameAuth'] = $this->iframeSuccessValue;

        $response = new JsonResponse([
            'apidata' => '/admin/plentymarkets?start=1',
        ]);

        return $response;
    }


    // Hier wird gerprüft, ob das Iframe im Admin angezeigt werden darf
    /**
     * @RouteScope(scopes={"administration"})
     * @Route("/admin/plentymarkets", defaults={"auth_required"=false}, name="api.admin.plentymarkets", methods={"GET", "POST"})
     */
    public function iframe(Request $request, Context $context)
    {
        session_cache_limiter('');
        session_name('neofire_sid');
        session_start();

        $config =  $this->systemConfigService->get('NeofirePlentymarketsDebugger.config');

        if($request->get('key') == $config['securekey']){
            $_SESSION['NeofireiFrameAuth'] = $this->iframeSuccessValue;
        }

        if($_SESSION['NeofireiFrameAuth'] == $this->iframeSuccessValue) {

            $this->import($request, $context);
            exit();

        }else{
            
            $error_template = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets_error.html.twig');
            $response = $this->render($error_template, ['output' => 'Keine Admin Authentifizierung!']);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }
    }
    

    // Hier werden alle Aufgaben erledigt.
    public function import(Request $request, Context $context)
    {

        $startzeit = time();

        $output = '';
        $info = '';
        
        
        //pentymarkets token holen
        $token = $this->plentymarketsBase->getCurrentAccessToken();

        // falls kein plentymarkets token exisitiert
        if(empty($token)){

            $error_template = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets_error.html.twig');
            $response = $this->render($error_template, ['output' => 'plentymarkets: keine Api Rechte!']);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();

        }


        // Übersichtsseite mit Buttons
        if(!empty($request->get('start'))){

            $overview_template = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets_overview.html.twig');
            $response = $this->render($overview_template, ['output' => $output]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Neustart vom Datenabgleich
        $output = $this->plentymarketsRestarts->import($request->get('restart'));

        if(!empty($output)){

            $overview_template = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets_overview.html.twig');
            $response = $this->render($overview_template, ['output' => $output]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Live Abfrage mit Details

        if($request->get('orderid', '') !== ''){


            $this->plentymarketsOrders->import('1');
            $list = $this->plentymarketsOrders->getOutput();

            $output = '<div style="position:fixed;top:60px;">Shopware V.6.1<br><br><br><a class="ankerlink" href="#$shopware"><i class="fab fa-shopware"></i> Bestelldaten</a><div class="ankerdesc">Übersicht der Bestelldaten, wie sie von plentymarkets ausgegeben werden.</div>';

            if(is_array($list['plenty_account'])){
                $output .= '<br><a class="ankerlink" href="#plenty_account_address"><i class="fas fa-user"></i>Account</a><div class="ankerdesc">Übersicht welche Daten für den Account an plentymarkets gesendet werden.</div>';
            }

            $output .= '<br><a class="ankerlink2" href="#$plenty_account"><i class="fas fa-file-invoice"></i> Account</a><div class="ankerdesc">Übersicht welche Daten für den Account an plentymarkets gesendet werden. <br><br><a class="smallicon" href="#$plenty_account_result"><i class="fas fa-trophy"></i>Ergebnis von Account</a></div>';

            $output .= '
            <br><a class="ankerlink2" href="#$plenty_billing_address"><i class="fas fa-file-invoice"></i> Rechnungsadresse</a><div class="ankerdesc">Übersicht welche Daten für die Rechnungsadresse an plentymarkets gesendet werden. <br><br><a class="smallicon" href="#$plenty_billing_address_result"><i class="fas fa-trophy"></i>Ergebnis von Rechnungsadresse</a></div>
            <br><a class="ankerlink2" href="#$plenty_shipping_address"><i class="fas fa-dolly"></i> Lieferadresse</a><div class="ankerdesc">Übersicht welche Daten für die Lieferadresse an plentymarkets gesendet werden. <br><br><a class="smallicon" href="#$plenty_shipping_address_result"><i class="fas fa-trophy"></i>Ergebnis von Lieferadresse</a></div>
            <br><a class="ankerlink2" href="#$plenty_order"><i class="fas fa-cash-register"></i> Bestellung</a><div class="ankerdesc">Übersicht welche Daten für die Bestellung an plentymarkets gesendet werden. <br><br><a class="smallicon" href="#$plenty_order_result"><i class="fas fa-trophy"></i>Ergebnis von Bestellung</a></div>';

            if(is_array($list['plenty_notice'])){
                $output .= '<br><a class="ankerlink2" href="#$plenty_notice"><i class="fas fa-sticky-note"></i>Notiz</a><div class="ankerdesc">Übersicht welche Daten für die Notiz an plentymarkets gesendet werden. <br><br><a class="smallicon" href="#$plenty_notice_result"><i class="fas fa-trophy"></i>Ergebnis von Notiz</a></div>';
            }

            if(is_array($list['plenty_payment'])){
                $output .= '<br><a class="ankerlink2" href="#$plenty_payment"><i class="far fa-credit-card"></i>Zahlung</a><div class="ankerdesc">Übersicht welche Daten für die Zahlung an plentymarkets gesendet werden. <br><br><a class="smallicon" href="#$plenty_payment_result"><i class="fas fa-trophy"></i>Ergebnis von Zahlung</a></div>';
            }
            $output .= '</div></div><div class="arraycontent">';


            foreach($list as $entry) {


                if(is_array($entry['shopware'])){

                    $output .= '<h3 id="$shopware"><i class="fas fa-share"></i>' . $entry['shopware']['name'] . '</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['shopware']['array'], '$shopware');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_account'])){

                    $output .= '<h3 id="$plenty_account"><i class="fas fa-share"></i>'.$entry['plenty_account']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_account']['array'], '$plenty_account');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_account_result'])){

                    $output .= '<h3 id="$plenty_account_result"><i class="fas fa-share"></i>'.$entry['plenty_account_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_account_result']['array'], '$plenty_account_result');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_account_address'])){

                    $output .= '<h3 id="$plenty_account_address"><i class="fas fa-share"></i>'.$entry['plenty_account_address']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_account_address']['array'], '$plenty_account_address');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_account_address_result'])){

                    $output .= '<h3 id="$plenty_account_address_result"><i class="fas fa-share"></i>'.$entry['plenty_account_address_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_account_address_result']['array'], '$plenty_account_address_result');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_billing_address'])){

                    $output .= '<h3 id="$plenty_billing_address"><i class="fas fa-share"></i>'.$entry['plenty_billing_address']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_billing_address']['array'], '$plenty_billing_address');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_billing_address_result'])){

                    $output .= '<h3 id="$plenty_billing_address_result"><i class="fas fa-share"></i>'.$entry['plenty_billing_address_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_billing_address_result']['array'], '$plenty_billing_address_result');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_shipping_address'])){

                    $output .= '<h3 id="$plenty_shipping_address"><i class="fas fa-share"></i>'.$entry['plenty_shipping_address']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_shipping_address']['array'], '$plenty_shipping_address');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_shipping_address_result'])){

                    $output .= '<h3 id="$plenty_shipping_address_result"><i class="fas fa-share"></i>'.$entry['plenty_shipping_address_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_shipping_address_result']['array'], '$plenty_shipping_address_result');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_order'])){

                    $output .= '<h3 id="$plenty_order"><i class="fas fa-share"></i>'.$entry['plenty_order']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_order']['array'], '$plenty_order');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_order_result'])){

                    $output .= '<h3 id="$plenty_order_result"><i class="fas fa-share"></i>'.$entry['plenty_order_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_order_result']['array'], '$plenty_order_result');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_notice'])){

                    $output .= '<h3 id="$plenty_notice"><i class="fas fa-share"></i>'.$entry['plenty_notice']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_notice']['array'], '$plenty_notice');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_notice_result'])){

                    $output .= '<h3 id="$plenty_notice_result"><i class="fas fa-share"></i>'.$entry['plenty_notice_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_notice_result']['array'], '$plenty_notice_result');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_payment'])){

                    $output .= '<h3 id="$plenty_payment"><i class="fas fa-share"></i>'.$entry['plenty_payment']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_payment']['array'], '$plenty_payment');
                    $output .= '</pre>';

                }

                if(is_array($entry['plenty_payment_result'])){

                    $output .= '<h3 id="$plenty_payment_result"><i class="fas fa-share"></i>'.$entry['plenty_payment_result']['name'].'</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['plenty_payment_result']['array'], '$plenty_payment_result');
                    $output .= '</pre>';

                }


            }

        }

        if($request->get('catid', '') !== ''){
            $output .= '<div style="position:fixed;top:60px;">Shopware V.6<br><br><br><a class="ankerlink" href="#kategoriedaten"><i class="fas fa-code"></i> Kategoriedaten</a><div class="ankerdesc">Übersicht der Kategoriedaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#shopwaredaten"><i class="fas fa-share"></i> Übertragung</a><div class="ankerdesc">Übersicht welche Daten zu Shopware gesendet werden.</div><br><a class="ankerlink" href="#success"><i class="fas fa-trophy"></i> Ergebnis</a><div class="ankerdesc">Hier wird angezeigt, ob alles geklappt hat.</div><br><a class="ankerlink" href="#vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau</a><div class="ankerdesc">Übersicht wie die Kategorie aktuell ist.</div></div></div><div class="arraycontent">';

            $this->plentymarketsCategories->import('1');
            $list = $this->plentymarketsCategories->getOutput();


            foreach($list as $entry){

                //Rest Api Route
                $output .= '<h3 id="kategoriedaten"><i class="fas fa-code"></i> Kategoriedaten:</h3><button style="float:right;" onclick="click1()">COPY RestApi URL</button>
                            <script>
                                function click1() {
                                    var dummy = document.createElement("textarea");
                                    document.body.appendChild(dummy);
                                    dummy.value = \''.$url.'\';
                                    dummy.select(); document.execCommand("copy");
                                    document.body.removeChild(dummy);
                                }
                            </script>';

                //Abfrage
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['call'], '$plenty_cat');
                $output .= '</pre>';

                //Übertragung
                $output .= '<h3 id="shopwaredaten"><i class="fas fa-share"></i> Übertragung:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['send'], '$shopware');
                $output .= '</pre>';
                $output .= '<h3 id="success"><i class="fas fa-trophy"></i> Ergebnis:</h3><br>';

                //Ergebnis
                $output .= '<br>'.$entry['name'].' -> <a href="?catid='.$entry['id'].'" target="_blank" rel="noopener">Kategorie ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';

                    $output .= '<br><br><h3 id="vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau:</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['get'], 'Vorschau');
                    $output .= '</pre>';

                }

            }

        }

        if($request->get('deliveryid', '') !== ''){
            $output .= '<div style="position:fixed;top:60px;">Shopware V.6<br><br><br><a class="ankerlink" href="#lieferzeitdaten"><i class="fas fa-code"></i> Lieferzeitdaten</a><div class="ankerdesc">Übersicht der Lieferezeitdaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#shopwaredaten"><i class="fas fa-share"></i> Übertragung</a><div class="ankerdesc">Übersicht welche Daten zu Shopware gesendet werden.</div><br><a class="ankerlink" href="#success"><i class="fas fa-trophy"></i> Ergebnis</a><div class="ankerdesc">Hier wird angezeigt, ob alles geklappt hat.</div><br><a class="ankerlink" href="#vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau</a><div class="ankerdesc">Übersicht wie die Lieferzeit aktuell ist.</div></div></div><div class="arraycontent">';

            $this->plentymarketsDeliveryTime->import('1');

            $list = $this->plentymarketsDeliveryTime->getOutput();

            foreach($list as $entry){

                //Rest Api Route
                $output .= '<h3 id="lieferdaten"><i class="fas fa-code"></i> Lieferdaten:</h3><button style="float:right;" onclick="click1()">COPY RestApi URL</button>
                                <script>
                                    function click1() {
                                        var dummy = document.createElement("textarea");
                                        document.body.appendChild(dummy);
                                        dummy.value = \''.$url.'\';
                                        dummy.select(); document.execCommand("copy");
                                        document.body.removeChild(dummy);
                                    }
                                </script>';

                //Abfrage
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['call'], '$plenty_deliverytime');
                $output .= '</pre>';

                //Übertragung
                $output .= '<h3 id="shopwaredaten"><i class="fas fa-share"></i> Übertragung:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['send'], '$shopware');
                $output .= '</pre>';
                $output .= '<h3 id="success"><i class="fas fa-trophy"></i> Ergebnis:</h3><br>';

                //Ergebnis
                $output .= '<br>'.$entry['name'].' -> <a href="?deliveryid='.$entry['id'].'" target="_blank" rel="noopener">Lieferzeit ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';

                    $output .= '<br><br><h3 id="vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau:</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['get'], 'Vorschau');
                    $output .= '</pre>';

                }

            }


        }

        if($request->get('manufacturerid', '') !== ''){
            $output .= '<div style="position:fixed;top:60px;">Shopware V.6<br><br><br><a class="ankerlink" href="#herstellerdaten"><i class="fas fa-code"></i> Herstellerdaten</a><div class="ankerdesc">Übersicht der Kategoriedaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#shopwaredaten"><i class="fas fa-share"></i> Übertragung</a><div class="ankerdesc">Übersicht welche Daten zu Shopware gesendet werden.</div><br><a class="ankerlink" href="#success"><i class="fas fa-trophy"></i> Ergebnis</a><div class="ankerdesc">Hier wird angezeigt, ob alles geklappt hat.</div><br><a class="ankerlink" href="#vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau</a><div class="ankerdesc">Übersicht wie der Hersteller aktuell ist.</div></div></div><div class="arraycontent">';

            $this->plentymarketsManufacturers->import('1');

            $list = $this->plentymarketsManufacturers->getOutput();

            foreach($list as $entry){

                //Rest Api Route
                $output .= '<h3 id="herstellerdaten"><i class="fas fa-code"></i> Herstellerdaten:</h3><button style="float:right;" onclick="click1()">COPY RestApi URL</button>
                            <script>
                                function click1() {
                                    var dummy = document.createElement("textarea");
                                    document.body.appendChild(dummy);
                                    dummy.value = \''.$url.'\';
                                    dummy.select(); document.execCommand("copy");
                                    document.body.removeChild(dummy);
                                }
                            </script>';

                //Abfrage
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['call'], '$plenty_manufacturer');
                $output .= '</pre>';

                //Übertragung
                $output .= '<h3 id="shopwaredaten"><i class="fas fa-share"></i> Übertragung:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['send'], '$shopware');
                $output .= '</pre>';
                $output .= '<h3 id="success"><i class="fas fa-trophy"></i> Ergebnis:</h3><br>';

                //Ergebnis
                $output .= '<br>'.$entry['name'].' -> <a href="?manufacturerid='.$entry['id'].'" target="_blank" rel="noopener">Hersteller ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';

                    $output .= '<br><br><h3 id="vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau:</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['get'], 'Vorschau');
                    $output .= '</pre>';

                }

            }


        }

        if($request->get('unitid', '') !== ''){
            $output .= '<div style="position:fixed;top:60px;">Shopware V.6<br><br><br><a class="ankerlink" href="#unitdaten"><i class="fas fa-code"></i> Unitdaten</a><div class="ankerdesc">Übersicht der Unitdaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#shopwaredaten"><i class="fas fa-share"></i> Übertragung</a><div class="ankerdesc">Übersicht welche Daten zu Shopware gesendet werden.</div><br><a class="ankerlink" href="#success"><i class="fas fa-trophy"></i> Ergebnis</a><div class="ankerdesc">Hier wird angezeigt, ob alles geklappt hat.</div><br><a class="ankerlink" href="#vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau</a><div class="ankerdesc">Übersicht wie der Hersteller aktuell ist.</div></div></div><div class="arraycontent">';

            $this->plentymarketsUnits->import('1');

            $list = $this->plentymarketsUnits->getOutput();

            foreach($list as $entry){

                //Rest Api Route
                $output .= '<h3 id="unitdaten"><i class="fas fa-code"></i> Unitdaten:</h3><button style="float:right;" onclick="click1()">COPY RestApi URL</button>
                            <script>
                                function click1() {
                                    var dummy = document.createElement("textarea");
                                    document.body.appendChild(dummy);
                                    dummy.value = \''.$url.'\';
                                    dummy.select(); document.execCommand("copy");
                                    document.body.removeChild(dummy);
                                }
                            </script>';

                //Abfrage
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['call'], '$plenty_unit');
                $output .= '</pre>';

                //Übertragung
                $output .= '<h3 id="shopwaredaten"><i class="fas fa-share"></i> Übertragung:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['send'], '$shopware');
                $output .= '</pre>';
                $output .= '<h3 id="success"><i class="fas fa-trophy"></i> Ergebnis:</h3><br>';

                //Ergebnis
                $output .= '<br>'.$entry['name'].' -> <a href="?unitid='.$entry['id'].'" target="_blank" rel="noopener">Unit ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';

                    $output .= '<br><br><h3 id="vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau:</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['get'], 'Vorschau');
                    $output .= '</pre>';

                }

            }
        }

        if($request->get('id', '') !== '') {
            $output .= '<div style="position:fixed;top:60px;">Shopware V.6<br><br><br><a class="ankerlink" href="#variationsdaten"><i class="fas fa-code"></i> Variationsdaten</a><div class="ankerdesc">Übersicht der Artikelvariantendaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#artikeldaten"><i class="fas fa-code"></i> Artikeldaten</a><div class="ankerdesc">Übersicht der Artikeldaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#bilder"><i class="fas fa-code"></i>Bilder</a><div class="ankerdesc">Übersicht der Bilder, wie sie verarbeitet werden.</div><br><a class="ankerlink" href="#shopwaredaten"><i class="fas fa-share"></i> Übertragung</a><div class="ankerdesc">Übersicht welche Daten zu Shopware gesendet werden.</div><br><a class="ankerlink" href="#success"><i class="fas fa-trophy"></i> Ergebnis</a><div class="ankerdesc">Hier wird angezeigt, ob alles geklappt hat.</div><br><a class="ankerlink" href="#vorschaudaten"><i class="fab fa-shopware"></i> Shopware Vorschau</a><div class="ankerdesc">Übersicht wie der Artikel aktuell ist.</div><br><br>Ladezeit:'.round((microtime(true) - $startzeit), 2).'</div></div><div class="arraycontent">';

            $this->plentymarketsProduct->product($request->get('id', ''), '', '', '1','',$startzeit);

            $list = $this->plentymarketsProduct->getOutput();



            foreach($list as $entry){

                //Abfrage
                $output .= '<h3 id="variationsdaten"><i class="fas fa-code"></i> Variationsdaten:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['plenty_variation'], '$plenty_variation');
                $output .= '</pre>';

                //Abfrage
                $output .= '<h3 id="artikeldaten"><i class="fas fa-code"></i> Artikeldaten:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['plenty_article'], '$plenty_article');
                $output .= '</pre>';

                //Bilder
                $output .= '<h3 id="bilder"><i class="fas fa-image"></i> Bilder:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['images'], '$images');
                $output .= '</pre>';

                //Übertragung
                $output .= '<h3 id="shopwaredaten"><i class="fas fa-share"></i> Übertragung:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['shopware'], '$shopware');
                $output .= '</pre>';
                $output .= '<h3 id="success"><i class="fas fa-trophy"></i> Ergebnis:</h3><br>';


                //Ergebnis
                $output .= '<br>'.$entry['name'].' -> <a href="?id='.$entry['id'].'" target="_blank" rel="noopener">Varianten ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';

                    $output .= '<br><br><h3 id="vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau:</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['get'], 'Vorschau');
                    $output .= '</pre>';

                }


            }
        }

        if($request->get('stockid', '') !== '') {

            $output .= '<div style="position:fixed;top:60px;">Shopware V.6<br><br><br><a class="ankerlink" href="#variationsdaten"><i class="fas fa-code"></i> Variationsdaten</a><div class="ankerdesc">Übersicht der Artikelvariantendaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#artikeldaten"><i class="fas fa-code"></i> Artikeldaten</a><div class="ankerdesc">Übersicht der Artikeldaten, wie sie von plentymarkets ausgegeben werden.</div><br><a class="ankerlink" href="#bilder"><i class="fas fa-code"></i>Bilder</a><div class="ankerdesc">Übersicht der Bilder, wie sie verarbeitet werden.</div><br><a class="ankerlink" href="#shopwaredaten"><i class="fas fa-share"></i> Übertragung</a><div class="ankerdesc">Übersicht welche Daten zu Shopware gesendet werden.</div><br><a class="ankerlink" href="#success"><i class="fas fa-trophy"></i> Ergebnis</a><div class="ankerdesc">Hier wird angezeigt, ob alles geklappt hat.</div><br><a class="ankerlink" href="#vorschaudaten"><i class="fab fa-shopware"></i> Shopware Vorschau</a><div class="ankerdesc">Übersicht wie der Artikel aktuell ist.</div><br><br>Ladezeit:'.round((microtime(true) - $startzeit), 2).'</div></div><div class="arraycontent">';

            $this->plentymarketsProduct->stock($request->get('stockid', ''), '', '1','');

            $list = $this->plentymarketsProduct->getOutput();


            foreach($list as $entry){

                //Abfrage
                $output .= '<h3 id="variationsdaten"><i class="fas fa-code"></i> Variationsdaten:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['plenty_variation'], '$plenty_variation');
                $output .= '</pre>';

                //Übertragung
                $output .= '<h3 id="shopwaredaten"><i class="fas fa-share"></i> Übertragung:</h3><br>';
                $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                $output .= $this->varToHtml($entry['stock'], '$stock');
                $output .= '</pre>';
                $output .= '<h3 id="success"><i class="fas fa-trophy"></i> Ergebnis:</h3><br>';


                //Ergebnis
                $output .= '<br>'.$entry['name'].' -> <a href="?id='.$entry['id'].'" target="_blank" rel="noopener">Varianten ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';

                    $output .= '<br><br><h3 id="vorschaudaten"><i class="fab fa-shopware"></i> Shopware 6 Vorschau:</h3><br>';
                    $output .= '<pre style="background:#f5f5f5;padding:10px;border: solid 1px #eaeaea;">';
                    $output .= $this->varToHtml($entry['get'], 'Vorschau');
                    $output .= '</pre>';

                }


            }
        }

        if(!empty($output)){

            $live_template = $this->finder->find('@Administration/administration/page/content/neofire_liveoutput.html.twig');
            $response = $this->render($live_template, ['output' => $output,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();

        }

        //Abfrage, ob ein Neustart angefordert wurde
        $builder = $this->dbal->createQueryBuilder();
        $builder->select('*')->from('NeofirePlentymarketsConnector_times')->where('success = :success');
        $builder->setParameter(':success', '0');
        $stmt = $builder->execute();
        $job = $stmt->fetch();
        $stmt->closeCursor();


        //Kategorien anlegen
        if($job['typ'] == 'categories'){


            $this->plentymarketsCategories->import('');

            $list = $this->plentymarketsCategories->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> <a href="?catid='.$entry['id'].'" target="_blank" rel="noopener">Kategorie ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');

            $response = $this->render($html_output, ['output' => $output,'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Lieferzeiten anlegen
        if($job['typ'] == 'deliverytimes') {

            $this->plentymarketsDeliveryTime->import('');

            $list = $this->plentymarketsDeliveryTime->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> <a href="?deliveryid='.$entry['id'].'" target="_blank" rel="noopener">Lieferzeit ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output, 'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Hersteller anlegen
        if($job['typ'] == 'manufacturers') {
            $this->plentymarketsManufacturers->import('');

            $list = $this->plentymarketsManufacturers->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> <a href="?manufacturerid='.$entry['id'].'" target="_blank" rel="noopener">Hersteller ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output, 'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Units anlegen
        if($job['typ'] == 'units') {

            $this->plentymarketsUnits->import('');

            $list = $this->plentymarketsUnits->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> <a href="?unitid='.$entry['id'].'" target="_blank" rel="noopener">Unit ID:'.$entry['id'].'</a>';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output, 'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Steuersätze anlegen
        if($job['typ'] == 'tax') {

            $this->plentymarketsTax->import('');

            $list = $this->plentymarketsTax->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' Mwst. -> Tax ID:'.$entry['id'];

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output, 'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Merkmale anlegen
        if($job['typ'] == 'poperties') {

            $this->plentymarketsAttributes->import('');

            $list = $this->plentymarketsAttributes->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> Merkmal ID:'.$entry['id'].'';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output,'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Eigenschaften anlegen
        if($job['typ'] == 'variationpoperties') {

            $this->plentymarketsVariationAttributes->import('');

            $list = $this->plentymarketsVariationAttributes->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> Varianten-Merkmal ID:'.$entry['id'].'';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output,'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Felder anlegen
        if($job['typ'] == 'fields') {

            $this->plentymarketsFields->import('');

            $list = $this->plentymarketsFields->getOutput();

            foreach($list as $entry){

                $output .= '<br>'.$entry['name'].' -> Feld ID:'.$entry['id'].'';

                if($entry['error'] == '1'){
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                }else{
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }

            }

            $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
            $response = $this->render($html_output, ['output' => $output,'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
            $response->headers->set('X-Frame-Options', 'SameOrigin');
            $response->send();
            exit();
        }


        //Bestellungen werden zu plentymarkets übertragen
        $output .= $this->plentymarketsOrders->import('');

        //Hier werden die Bestände aktualisiert
        $info .= '<br>'.$this->plentymarketsStock->getData('');
        $this->plentymarketsData->import($startzeit,'stock');


        //Hier werden die Produkte aktualisiert
        $info .= '<br>'.$this->plentymarketsData->getData();
        $this->plentymarketsData->import($startzeit,'items');


        $list = $this->plentymarketsProduct->getOutput();


        foreach($list as $entry){

            if(isset($entry['id'])) {

                $output .= '<br>'. $entry['name'] . ' -> <a href="?id=' . $entry['id'] . '" target="_blank" rel="noopener">Varianten ID:' . $entry['id'] . '</a>';

                if ($entry['error'] == '1') {
                    $output .= '<font style="color:ff0000;"> Fehler!</font>';
                } else {
                    $output .= '<font style="color:00d10f;"> erfogleich geupdatet/angelegt!</font>';
                }
            }

        }


        if($output == ''){
            $output .= '<meta http-equiv="refresh" content="5"; URL="plentymarkets">';
            $output .= '<div class="checkmark"></div>';
        }

        $html_output = $this->finder->find('@Administration/administration/page/content/neofire_plentymarkets.html.twig');
        $response = $this->render($html_output, ['output' => $output,'info' => $info,'time' => round((microtime(true) - $startzeit),2)]);
        $response->headers->set('X-Frame-Options', 'SameOrigin');
        $response->send();
        exit();
    }

    public function varToHtml($var = '', $key = '')
    {
        $type = gettype($var);
        $result = '';

        if (in_array($type, ['object', 'array'])) {

            if (empty($key) && $key != '0') {
                $key = '$shopware';
            }
            $result .= '
        <table class="debug-table">
            <tr>
            <td class="debug-key-cell"><b style="color: #000; font-size:17px;">'.str_replace('*', '', $key).'</b></td>
            <td class="debug-value-cell">';

            foreach ($var as $akey => $val) {
                $result .= $this->varToHtml($val, $akey);
            }
            $result .= '</td></tr></table>';
        } else {

            if($key == 'NEOFIREINFO'){
                $result .= '<div class="debug-item"><span class="debug-value teilc">'.$var.'</span></div>';
            }else{
                $result .= '<div class="debug-item"><span class="debug-label teila">'.str_replace(
                        '*',
                        '',
                        $key
                    ).'</span><span class="debug-value teilb">'.$var.'</span></div>';
            }


        }

        return $result;
    }

    public function errorlog($id, $typ, $error)
    {

        if($typ == 'image'){
            $fehler = 'Bildproblem';
        }else{
            $fehler = 'Fehler';
        }

        $output = '<div onclick="var info = \''.$typ.'-error-'.$id.'\'; show(info);" class="errorinfo">'.$fehler.'</div><div id="'.$typ.'-error-'.$id.'" class="invisible">'.$error.'</div>';

        return $output;

    }
}