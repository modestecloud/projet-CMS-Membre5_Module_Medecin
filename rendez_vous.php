<?php
// ============================================================
//  Gestion des Rendez-vous Médecin
//  Fichier : medecin/rendez_vous.php
// ============================================================
$pageTitle = "Mes Rendez-vous";
require_once '../includes/fonctions.php';
require_once '../includes/connexion.php';
requireRole('medecin');

$stmt = $pdo->prepare("SELECT id FROM medecins WHERE utilisateur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$medecin    = $stmt->fetch();
$medecin_id = $medecin['id'];

// Confirmer un RDV
if (isset($_GET['confirmer']) && is_numeric($_GET['confirmer'])) {
    $stmt = $pdo->prepare("UPDATE rendez_vous SET statut='confirme' WHERE id=? AND medecin_id=?");
    $stmt->execute([(int)$_GET['confirmer'], $medecin_id]);

    // Notification au patient
    $stmt2 = $pdo->prepare("SELECT r.date_rdv, r.heure_rdv, pa.utilisateur_id FROM rendez_vous r JOIN patients pa ON r.patient_id=pa.id WHERE r.id=?");
    $stmt2->execute([(int)$_GET['confirmer']]);
    $info = $stmt2->fetch();
    if ($info) {
        $msg = "Votre rendez-vous du ".date('d/m/Y', strtotime($info['date_rdv']))." à ".date('H:i', strtotime($info['heure_rdv']))." a été confirmé par votre médecin.";
        $stmt3 = $pdo->prepare("INSERT INTO notifications (utilisateur_id, message, type) VALUES (?,?,'rappel_rdv')");
        $stmt3->execute([$info['utilisateur_id'], $msg]);
    }
    setFlash('success', 'Rendez-vous confirmé avec succès.');
    header("Location: rendez_vous.php");
    exit();
}

// Annuler un RDV
if (isset($_GET['annuler']) && is_numeric($_GET['annuler'])) {
    $stmt = $pdo->prepare("UPDATE rendez_vous SET statut='annule' WHERE id=? AND medecin_id=?");
    $stmt->execute([(int)$_GET['annuler'], $medecin_id]);

    $stmt2 = $pdo->prepare("SELECT r.date_rdv, pa.utilisateur_id FROM rendez_vous r JOIN patients pa ON r.patient_id=pa.id WHERE r.id=?");
    $stmt2->execute([(int)$_GET['annuler']]);
    $info = $stmt2->fetch();
    if ($info) {
        $msg = "Votre rendez-vous du ".date('d/m/Y', strtotime($info['date_rdv']))." a été annulé par votre médecin.";
        $stmt3 = $pdo->prepare("INSERT INTO notifications (utilisateur_id, message, type) VALUES (?,?,'annulation')");
        $stmt3->execute([$info['utilisateur_id'], $msg]);
    }
    setFlash('warning', 'Rendez-vous annulé.');
    header("Location: rendez_vous.php");
    exit();
}

// Filtre
$filtre = clean($_GET['statut'] ?? 'tous');
$where  = "WHERE r.medecin_id = ?";
$params = [$medecin_id];
if ($filtre !== 'tous') {
    $where  .= " AND r.statut = ?";
    $params[] = $filtre;
}

$stmt = $pdo->prepare("
    SELECT r.*, CONCAT(u.prenom, ' ', u.nom) AS patient_nom, p.matricule
    FROM rendez_vous r
    JOIN patients p ON r.patient_id = p.id
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    $where
    ORDER BY r.date_rdv DESC, r.heure_rdv ASC
");
$stmt->execute($params);
$rdvs = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="row">
    <!-- SIDEBAR -->
    <div class="col-md-3 col-lg-2 px-0">
        <div class="sidebar d-flex flex-column" style="min-height:85vh;">
            <div class="text-center text-white py-4 px-3 border-bottom border-secondary">
                <i class="bi bi-person-badge" style="font-size:3rem;"></i>
                <p class="mb-0 fw-bold mt-2">Dr. <?= htmlspecialchars($_SESSION['prenom'].' '.$_SESSION['nom']) ?></p>
                <small class="opacity-75">Médecin</small>
            </div>
            <nav class="nav flex-column mt-3">
                <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
                <a href="rendez_vous.php" class="nav-link active"><i class="bi bi-calendar-week"></i> Mes Rendez-vous</a>
                <a href="consultations.php" class="nav-link"><i class="bi bi-clipboard2-pulse"></i> Consultations</a>
                <a href="patients.php" class="nav-link"><i class="bi bi-people"></i> Mes Patients</a>
                <a href="profil.php" class="nav-link"><i class="bi bi-person-gear"></i> Mon Profil</a>
                <hr class="border-secondary mx-3">
                <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            </nav>
        </div>
    </div>

    <!-- CONTENU -->
    <div class="col-md-9 col-lg-10 py-3 px-4">

        <?php afficherFlash(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-0">
                    <i class="bi bi-calendar-week me-2"></i>Mes Rendez-vous
                </h4>
                <small class="text-muted"><?= count($rdvs) ?> rendez-vous trouvé(s)</small>
            </div>
        </div>

        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span class="fw-semibold text-muted me-2">Filtrer :</span>
                    <?php
                    $filtres = [
                        'tous'       => ['label' => 'Tous',       'class' => 'secondary'],
                        'en_attente' => ['label' => 'En attente', 'class' => 'warning'],
                        'confirme'   => ['label' => 'Confirmés',  'class' => 'success'],
                        'effectue'   => ['label' => 'Effectués',  'class' => 'info'],
                        'annule'     => ['label' => 'Annulés',    'class' => 'danger'],
                    ];
                    foreach ($filtres as $val => $opt):
                        $active = ($filtre === $val) ? 'btn-'.$opt['class'] : 'btn-outline-'.$opt['class'];
                    ?>
                        <a href="?statut=<?= $val ?>" class="btn btn-sm <?= $active ?>">
                            <?= $opt['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Tableau des RDV -->
        <?php if (!empty($rdvs)): ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Heure</th>
                                <th>Patient</th>
                                <th>Matricule</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rdvs as $rdv):
                                $badgeClass = [
                                    'en_attente' => 'warning',
                                    'confirme'   => 'success',
                                    'annule'     => 'danger',
                                    'effectue'   => 'info',
                                ];
                                $statut = $rdv['statut'];
                                $label  = ucfirst(str_replace('_', ' ', $statut));
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?></td>
                                <td><?= date('H:i', strtotime($rdv['heure_rdv'])) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($rdv['patient_nom']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($rdv['matricule'] ?? '—') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($rdv['motif'] ?? '—') ?></td>
                                <td>
                                    <span class="badge bg-<?= $badgeClass[$statut] ?? 'secondary' ?>">
                                        <?= $label ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <?php if ($statut === 'en_attente'): ?>
                                            <a href="?confirmer=<?= $rdv['id'] ?>&statut=<?= $filtre ?>"
                                               class="btn btn-sm btn-success"
                                               title="Confirmer"
                                               onclick="return confirm('Confirmer ce rendez-vous ?')">
                                                <i class="bi bi-check2"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (in_array($statut, ['en_attente','confirme'])): ?>
                                            <a href="consultations.php?rdv_id=<?= $rdv['id'] ?>"
                                               class="btn btn-sm btn-primary" title="Saisir consultation">
                                                <i class="bi bi-clipboard2-plus"></i>
                                            </a>
                                            <a href="?annuler=<?= $rdv['id'] ?>&statut=<?= $filtre ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               title="Annuler"
                                               onclick="return confirm('Annuler ce rendez-vous ?')">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar-x" style="font-size:4rem;opacity:0.2;"></i>
                <p class="mt-3">Aucun rendez-vous trouvé</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
