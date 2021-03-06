<?php

/**
 * Created by PhpStorm.
 * User: Francois
 * Date: 11/06/15
 * Time: 19:39
 */

class ApiController extends \Phalcon\Mvc\Controller
{
    private function verifyOauth() {
        $oa = new Oauthorize();
        return $oa->checkToken();
    }

    /**
     * Core route. Pick next_metro for given station
     * @post This route can be use with POST request
     *
     * @param (example)
     * JSON :
     *          {
     *              "linesNumber":"[1,2,3]",
     *              "station_name":"Opéra"
     *          }
     */
    public function nextMetroAction()
    {
        $this->response->setContentType('text/json');
        $inputLines = $this->request->getPost('linesNumber');
        $inputstation_name = $this->request->getPost('station_name');


        try {
            if (!$this->verifyOauth()) {
                echo json_encode(array("Error", "Token invalide"));
                return;
            }
            $token = $this->request->getPost('access_token');
            $user = Users::findFirstByToken($token);

            if ($user == null) {
                echo json_encode(array("Error", "Token introuvable"));
            } else {
                $conso = $this->modelsManager->createBuilder()
                    ->from('Comsumption')
                    ->join('Users', 'u.id = Comsumption.user', 'u')
                    ->join('Offers', 'o.id = u.offer', 'o')
                    ->where('Comsumption.datestamp = DATE(NOW()) AND u.token = \'' . $token . '\'')
                    ->columns('Comsumption.id as id, o.max_queries as maxqueries')
                    ->getQuery()->execute();

                if ($conso == null || count($conso) == 0) {
                    $consom = new Comsumption();
                    $consom->datestamp = (new DateTime())->format('Y-m-d');
                    $consom->user = $user->id;
                    $consom->conso = 1;
                    $consom->save();
                } else {
                    $max = $conso[0]->maxqueries;
                    $consom = Comsumption::findFirst($conso[0]->id);
                    if ($max <= $consom->conso) {
                        echo json_encode(array('Error', 'Consommation maximum atteinte (' . $consom->conso . ' / ' . $max . ')'));
                        return;
                    }
                    $consom->conso += 1;
                    $consom->save();
                }

                $linesNumber = json_decode($inputLines);
                $station_name = $inputstation_name;

                $output = array('serviceStatus' => RatpService::IsServiceUp() ? 'up' : 'down',
                    'stationName' => $station_name,
                    'requestLines' => $linesNumber,
                    'lines' => array());

                foreach ($linesNumber as $line) {
                    $output['lines'][$line] = array();
                    array_push($output['lines'][$line], array(
                            'a_way' => RatpService::GetNextMetroCached(Lines::findFirst('line_number=\'' . $line . '\'')->line_number, $station_name, 'A'),
                            'r_way' => RatpService::GetNextMetroCached(Lines::findFirst('line_number=\'' . $line . '\'')->line_number, $station_name, 'R')
                        )
                    );
                }

                echo json_encode($output);
            }
        }
        catch (Exception $e)
        {
            echo json_encode(array("Error", "Erreur interne"));
        }
    }

    public function requestTokenAction()
    {
        $this->response->setContentType('text/json');
        $input = $this->request->getJsonRawBody();
        $mail = json_decode($input->mail);

        $oauth = new Oauthorize();
        $token = $oauth->getNewToken($mail);
        echo json_encode(array('token' => $token));
    }
}