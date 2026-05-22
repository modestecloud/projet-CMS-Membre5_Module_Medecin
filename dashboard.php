<?php
// ============================================================
//  Tableau de bord Médecin
//  Fichier : medecin/dashboard.php
// ============================================================
$pageTitle = "Tableau de Bord Médecin";
require_once '../includes/fonctions.php';
require_once '../includes/connexion.php';
requireRole('medecin');

// Récupérer l'ID du médecin
$stmt = $pdo->prepare("SELECT id FROM medecins WHERE utilisateur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$medecin    = $stmt->fetch();
$medecin_id = $medecin['id'];

// RDV du jour
$stmt = $pdo->prepare("
    SELECT r.*, CONCAT(u.prenom, ' ', u.nom) AS patient_nom, p.matricule
    FROM rendez_vous r
    JOIN patients pa ON r.patient_id = pa.id
    JOIN utilisateurs u ON pa.utilisateur_id = u.id
    JOIN patients p ON r.patient_id = p.id
    WHERE r.medecin_id = ? AND r.date_rdv = CURDATE()
    AND r.statut IN ('en_attente','confirme')
    ORDER BY r.heure_rdv ASC
");
$stmt->execute([$medecin_id]);
$rdv_aujourd_hui = $stmt->fetchAll();

// Statistiques
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM rendez_vous WHERE medecin_id = ? AND date_rdv = CURDATE() AND statut IN ('en_attente','confirme')");
$stmt->execute([$medecin_id]);
$total_aujourd_hui = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM rendez_vous WHERE medecin_id = ? AND statut = 'en_attente'");
$stmt->execute([$medecin_id]);
$total_en_attente = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM rendez_vous WHERE medecin_id = ? AND statut = 'effectue'");
$stmt->execute([$medecin_id]);
$total_effectues = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) AS total FROM rendez_vous WHERE medecin_id = ?");
$stmt->execute([$medecin_id]);
$total_patients = $stmt->fetch()['total'];

// Prochains RDV (7 jours)
$stmt = $pdo->prepare("
    SELECT r.*, CONCAT(u.prenom, ' ', u.nom) AS patient_nom
    FROM rendez_vous r
    JOIN patients pa ON r.patient_id = pa.id
    JOIN utilisateurs u ON pa.utilisateur_id = u.id
    WHERE r.medecin_id = ?
    AND r.date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND r.statut IN ('en_attente','confirme')
    ORDER BY r.date_rdv ASC, r.heure_rdv ASC
    LIMIT 5
");
$stmt->execute([$medecin_id]);
$prochains_rdv = $stmt->fetchAll();

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
                <a href="dashboard.php" class="nav-link active">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>
                <a href="rendez_vous.php" class="nav-link">
                    <i class="bi bi-calendar-week"></i> Mes Rendez-vous
                </a>
                <a href="consultations.php" class="nav-link">
                    <i class="bi bi-clipboard2-pulse"></i> Consultations
                </a>
                <a href="patients.php" class="nav-link">
                    <i class="bi bi-people"></i> Mes Patients
                </a>
                <a href="profil.php" class="nav-link">
                    <i class="bi bi-person-gear"></i> Mon Profil
                </a>
                <hr class="border-secondary mx-3">
                <a href="../auth/logout.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </nav>
        </div>
    </div>

    <!-- CONTENU -->
    <div class="col-md-9 col-lg-10 py-3 px-4">

        <?php afficherFlash(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-0">
                    <i class="bi bi-speedometer2 me-2"></i>Tableau de Bord
                </h4>
                <small class="text-muted">
                    Bonjour Dr. <?= htmlspecialchars($_SESSION['prenom']) ?> !
                    <?= date('l d F Y') ?>
                </small>
            </div>
        </div>

        <!-- STATISTIQUES -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card" style="background:linear-gradient(135deg,#1A5276,#2E86C1);">
                    <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
                    <div class="stat-number"><?= $total_aujourd_hui ?></div>
                    <div>RDV Aujourd'hui</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="background:linear-gradient(135deg,#B7950B,#F39C12);">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?= $total_en_attente ?></div>
                    <div>En Attente</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="background:linear-gradient(135deg,#1E8449,#27AE60);">
                    <div class="stat-icon"><i class="bi bi-clipboard2-check"></i></div>
                    <div class="stat-number"><?= $total_effectues ?></div>
                    <div>Consultations</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="background:linear-gradient(135deg,#6C3483,#A569BD);">
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                    <div class="stat-number"><?= $total_patients ?></div>
                    <div>Patients</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- RDV DU JOUR -->
            <div class="col-md-7">
                <div class="card h-100">
                    <div class="card-header bg-white fw-bold text-primary border-bottom d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-calendar-day me-2"></i>Rendez-vous d'Aujourd'hui</span>
                        <span class="badge bg-primary"><?= count($rdv_aujourd_hui) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($rdv_aujourd_hui)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($rdv_aujourd_hui as $rdv): ?>
                                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                                    <div class="text-center" style="min-width:55px;">
                                        <span class="fw-bold text-primary fs-6">
                                            <?= date('H:i', strtotime($rdv['heure_rdv'])) ?>
                                        </span>
                                    </div>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                         style="width:40px;height:40px;flex-shrink:0;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="mb-0 fw-bold"><?= htmlspecialchars($rdv['patient_nom']) ?></p>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($rdv['motif'] ?? 'Consultation générale') ?>
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if ($rdv['statut'] === 'en_attente'): ?>
                                        <a href="rendez_vous.php?confirmer=<?= $rdv['id'] ?>"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Confirmer ce rendez-vous ?')">
                                            <i class="bi bi-check2"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="consultations.php?rdv_id=<?= $rdv['id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-clipboard2-plus"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-calendar-check" style="font-size:3rem;opacity:0.2;"></i>
                                <p class="mt-2">Aucun rendez-vous aujourd'hui</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PROCHAINS RDV -->
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-header bg-white fw-bold text-primary border-bottom d-flex justify-content-between">
                        <span><i class="bi bi-calendar-week me-2"></i>7 Prochains Jours</span>
                        <a href="rendez_vous.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($prochains_rdv)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($prochains_rdv as $rdv):
                                    $badgeClass = $rdv['statut'] === 'confirme' ? 'success' : 'warning';
                                    $label = $rdv['statut'] === 'confirme' ? 'Confirmé' : 'En attente';
                                ?>
                                <div class="list-group-item py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-0 fw-semibold small">
                                                <?= htmlspecialchars($rdv['patient_nom']) ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?>
                                                à <?= date('H:i', strtotime($rdv['heure_rdv'])) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= $badgeClass ?>"><?= $label ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-calendar-x" style="font-size:2.5rem;opacity:0.2;"></i>
                                <p class="mt-2 small">Aucun RDV prévu</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
