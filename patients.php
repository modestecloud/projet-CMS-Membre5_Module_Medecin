<?php
// ============================================================
//  Liste des patients du médecin
//  Fichier : medecin/patients.php
// ============================================================
$pageTitle = "Mes Patients";
require_once '../includes/fonctions.php';
require_once '../includes/connexion.php';
requireRole('medecin');

$stmt = $pdo->prepare("SELECT id FROM medecins WHERE utilisateur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$medecin    = $stmt->fetch();
$medecin_id = $medecin['id'];

// Recherche
$recherche = clean($_GET['q'] ?? '');

// Liste des patients ayant eu un RDV avec ce médecin
$sql = "
    SELECT DISTINCT p.id, p.matricule, p.groupe_sanguin, p.date_naissance, p.sexe,
           u.nom, u.prenom, u.email, u.telephone,
           COUNT(r.id) AS nb_rdv,
           MAX(r.date_rdv) AS dernier_rdv
    FROM patients p
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    JOIN rendez_vous r ON r.patient_id = p.id
    WHERE r.medecin_id = ?
";
$params = [$medecin_id];
if (!empty($recherche)) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR p.matricule LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}
$sql .= " GROUP BY p.id ORDER BY u.nom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Voir dossier d'un patient
$dossier_patient = null;
if (isset($_GET['patient_id']) && is_numeric($_GET['patient_id'])) {
    $pid = (int)$_GET['patient_id'];

    $stmt = $pdo->prepare("
        SELECT p.*, u.nom, u.prenom, u.email, u.telephone
        FROM patients p JOIN utilisateurs u ON p.utilisateur_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pid]);
    $dossier_patient = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT r.date_rdv, r.heure_rdv, r.motif, r.statut,
               c.diagnostic, c.traitement, c.notes,
               c.poids, c.taille, c.tension_arterielle, c.temperature
        FROM rendez_vous r
        LEFT JOIN consultations c ON c.rendez_vous_id = r.id
        WHERE r.patient_id = ? AND r.medecin_id = ?
        ORDER BY r.date_rdv DESC
    ");
    $stmt->execute([$pid, $medecin_id]);
    $dossier_rdvs = $stmt->fetchAll();
}

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
                <a href="rendez_vous.php" class="nav-link"><i class="bi bi-calendar-week"></i> Mes Rendez-vous</a>
                <a href="consultations.php" class="nav-link"><i class="bi bi-clipboard2-pulse"></i> Consultations</a>
                <a href="patients.php" class="nav-link active"><i class="bi bi-people"></i> Mes Patients</a>
                <a href="profil.php" class="nav-link"><i class="bi bi-person-gear"></i> Mon Profil</a>
                <hr class="border-secondary mx-3">
                <a href="../auth/logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            </nav>
        </div>
    </div>

    <!-- CONTENU -->
    <div class="col-md-9 col-lg-10 py-3 px-4">

        <?php if ($dossier_patient): ?>
        <!-- ===== DOSSIER PATIENT ===== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold text-primary mb-0">
                <i class="bi bi-folder2-open me-2"></i>
                Dossier de <?= htmlspecialchars($dossier_patient['prenom'].' '.$dossier_patient['nom']) ?>
            </h4>
            <a href="patients.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-header bg-white fw-bold text-primary border-bottom">
                        <i class="bi bi-person me-2"></i>Informations Patient
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless small mb-0">
                            <tr><td class="text-muted">Nom complet</td><td class="fw-semibold"><?= htmlspecialchars($dossier_patient['prenom'].' '.$dossier_patient['nom']) ?></td></tr>
                            <tr><td class="text-muted">Matricule</td><td><?= htmlspecialchars($dossier_patient['matricule'] ?? '—') ?></td></tr>
                            <tr><td class="text-muted">Date naissance</td><td><?= $dossier_patient['date_naissance'] ? date('d/m/Y', strtotime($dossier_patient['date_naissance'])) : '—' ?></td></tr>
                            <tr><td class="text-muted">Sexe</td><td><?= $dossier_patient['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?></td></tr>
                            <tr><td class="text-muted">Groupe sanguin</td><td><span class="badge bg-danger"><?= $dossier_patient['groupe_sanguin'] ?? '?' ?></span></td></tr>
                            <tr><td class="text-muted">Téléphone</td><td><?= htmlspecialchars($dossier_patient['telephone'] ?? '—') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card h-100">
                    <div class="card-header bg-white fw-bold text-primary border-bottom">
                        <i class="bi bi-heart-pulse me-2"></i>Antécédents & Allergies
                    </div>
                    <div class="card-body">
                        <p class="fw-semibold text-muted small mb-1">Allergies :</p>
                        <p><?= !empty($dossier_patient['allergies']) ? htmlspecialchars($dossier_patient['allergies']) : '<span class="text-muted fst-italic">Aucune</span>' ?></p>
                        <hr>
                        <p class="fw-semibold text-muted small mb-1">Antécédents médicaux :</p>
                        <p class="mb-0"><?= !empty($dossier_patient['antecedents']) ? htmlspecialchars($dossier_patient['antecedents']) : '<span class="text-muted fst-italic">Aucun</span>' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historique des RDV avec ce médecin -->
        <div class="card">
            <div class="card-header bg-white fw-bold text-primary border-bottom">
                <i class="bi bi-clock-history me-2"></i>Historique des consultations
            </div>
            <div class="card-body p-0">
                <?php if (!empty($dossier_rdvs)): ?>
                <div class="accordion" id="accRdv">
                    <?php foreach ($dossier_rdvs as $i => $r):
                        $badgeClass = ['en_attente'=>'warning','confirme'=>'success','annule'=>'danger','effectue'=>'info'];
                    ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?> py-2"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#rdv<?= $i ?>">
                                <span class="me-3"><?= date('d/m/Y', strtotime($r['date_rdv'])) ?></span>
                                <span class="badge bg-<?= $badgeClass[$r['statut']] ?? 'secondary' ?> me-2">
                                    <?= ucfirst(str_replace('_',' ',$r['statut'])) ?>
                                </span>
                                <small class="text-muted"><?= htmlspecialchars($r['motif'] ?? '') ?></small>
                            </button>
                        </h2>
                        <div id="rdv<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>">
                            <div class="accordion-body bg-light">
                                <?php if ($r['diagnostic']): ?>
                                    <p class="mb-1"><strong>Diagnostic :</strong> <?= htmlspecialchars($r['diagnostic']) ?></p>
                                    <p class="mb-1"><strong>Traitement :</strong> <?= htmlspecialchars($r['traitement'] ?? '—') ?></p>
                                    <?php if ($r['poids'] || $r['taille'] || $r['tension_arterielle']): ?>
                                    <p class="mb-0 text-muted small">
                                        <?php if ($r['poids']): ?>Poids: <?= $r['poids'] ?>kg &nbsp;<?php endif; ?>
                                        <?php if ($r['taille']): ?>Taille: <?= $r['taille'] ?>cm &nbsp;<?php endif; ?>
                                        <?php if ($r['tension_arterielle']): ?>Tension: <?= htmlspecialchars($r['tension_arterielle']) ?> &nbsp;<?php endif; ?>
                                        <?php if ($r['temperature']): ?>Temp: <?= $r['temperature'] ?>°C<?php endif; ?>
                                    </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted fst-italic mb-0">Aucune consultation enregistrée pour ce RDV.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <p>Aucun historique disponible.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- ===== LISTE DES PATIENTS ===== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-0">
                    <i class="bi bi-people me-2"></i>Mes Patients
                </h4>
                <small class="text-muted"><?= count($patients) ?> patient(s)</small>
            </div>
        </div>

        <!-- Recherche -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" action="">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q"
                               placeholder="Rechercher par nom, prénom ou matricule..."
                               value="<?= htmlspecialchars($recherche) ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if ($recherche): ?>
                            <a href="patients.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($patients)): ?>
        <div class="row g-3">
            <?php foreach ($patients as $pat): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center
                                    justify-content-center flex-shrink-0"
                             style="width:50px;height:50px;font-size:1.3rem;">
                            <?= strtoupper(substr($pat['prenom'],0,1).substr($pat['nom'],0,1)) ?>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-0 fw-bold"><?= htmlspecialchars($pat['prenom'].' '.$pat['nom']) ?></p>
                            <small class="text-muted">
                                <?= htmlspecialchars($pat['matricule'] ?? 'Sans matricule') ?>
                                <?php if ($pat['groupe_sanguin']): ?>
                                    &nbsp;|&nbsp;<span class="badge bg-danger"><?= $pat['groupe_sanguin'] ?></span>
                                <?php endif; ?>
                            </small>
                            <br>
                            <small class="text-muted">
                                <i class="bi bi-calendar-check me-1"></i><?= $pat['nb_rdv'] ?> RDV
                                &nbsp;|&nbsp;Dernier : <?= date('d/m/Y', strtotime($pat['dernier_rdv'])) ?>
                            </small>
                        </div>
                        <a href="patients.php?patient_id=<?= $pat['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-folder2-open"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-people" style="font-size:4rem;opacity:0.2;"></i>
                <p class="mt-3">Aucun patient trouvé</p>
            </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
