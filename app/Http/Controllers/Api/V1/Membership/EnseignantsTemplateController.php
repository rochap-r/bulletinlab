<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modèle Excel vierge pour l'import des enseignants (sans colonne rôle :
 * l'import ne crée que des enseignants, le titulariat est nommé par classe).
 */
final class EnseignantsTemplateController extends Controller
{
    /** @var list<string> */
    private const HEADERS = [
        'nom',
        'postnom',
        'prenom',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'email',
        'telephone',
        'adresse',
        'matricule',
        'qualification',
        'specialite',
        'date_embauche',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        /** @var Utilisateur|null $actor */
        $actor = $request->user();

        abort_unless($actor?->can('create', Utilisateur::class) === true, 403);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Enseignants');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        // Valeurs écrites en texte explicite : `+243...` serait sinon
        // converti en nombre par PhpSpreadsheet.
        $examples = [
            ['Ilunga', 'Mbuyi', 'Sara', 'F', '1990-05-14', 'Kinshasa', 'sara.ilunga@example.com', '+243811000501', 'Av. de la Paix 12', 'ENS-001', 'Licenciée en mathématiques', 'Mathématiques', '2022-09-01'],
            ['Kabasele', '', 'Jean', 'M', '', '', '', '+243811000502', '', 'ENS-002', 'D6 Pédagogie', 'Français', ''],
        ];
        foreach ($examples as $rowIndex => $example) {
            foreach ($example as $columnIndex => $value) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2),
                    $value,
                    DataType::TYPE_STRING,
                );
            }
        }

        // Format texte sur la colonne téléphone pour les exemples comme
        // pour les lignes saisies par l'utilisateur.
        $telephoneColumn = array_search('telephone', self::HEADERS, true) + 1;
        $telephoneLetter = Coordinate::stringFromColumnIndex($telephoneColumn);
        $sheet->getStyle($telephoneLetter.'2:'.$telephoneLetter.'501')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);

        foreach (range(1, count(self::HEADERS)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'modele-import-enseignants.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
