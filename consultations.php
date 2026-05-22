<?php
// ============================================================
//  Saisie et historique des consultations
//  Fichier : medecin/consultations.php
// ============================================================
$pageTitle = "Consultations";
require_once '../includes/fonctions.php';
require_once '../includes/connexion.php';
requireRole('medecin');

$stmt = $pdo->prepare("SELECT id FROM medecins WHERE utilisateur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$medecin    = $stmt->fetch();
$medecin_id = $medecin['id'];

$rdv_id  = isset($_GET['rdv_id']) ? (int)$_GET['rdv_id'] : 0;
$erreurs = [];
$rdv     = null;

// Charger le RDV si fourni
if ($rdv_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, CONCAT(u.prenom, ' ', u.nom) AS patient_nom,
               p.id AS patient_id, p.groupe_sanguin, p.allergies
        FROM rendez_vous r
        JOIN patients p ON r.patient_id = p.id
        JOIN utilisateurs u ON p.utilisateur_id = u.id
        WHERE r.id = ? AND r.medecin_id = ?
    ");
    $stmt->execute([$rdv_id, $medecin_id]);
    $rdv = $stmt->fetch();
}

// Enregistrement de la consultation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rdv_id_post   = (int)($_POST['rdv_id'] ?? 0);
    $diagnostic    = clean($_POST['diagnostic'] ?? '');
    $traitement    = clean($_POST['traitement'] ?? '');
    $notes         = clean($_POST['notes'] ?? '');
    $poids         = clean($_POST['poids'] ?? '');
    $taille        = clean($_POST['taille'] ?? '');
    $tension       = clean($_POST['tension_arterielle'] ?? '');
    $temperature   = clean($_POST['temperature'] ?? '');
    $medicaments   = $_POST['medicament'] ?? [];
    $posologies    = $_POST['posologie'] ?? [];
    $durees        = $_POST['duree'] ?? [];
    $instructions  = $_POST['instructions'] ?? [];

    if (empty($diagnostic)) $erreurs[] = "Le diagnostic est obligatoire.";

    if (empty($erreurs)) {
        // Vérifier si consultation existe déjà
        $stmt = $pdo->prepare("SELECT id FROM consultations WHERE rendez_vous_id = ?");
        $stmt->execute([$rdv_id_post]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Mettre à jour
            $stmt = $pdo->prepare("UPDATE consultations SET diagnostic=?, traitement=?, notes=?,
                poids=?, taille=?, tension_arterielle=?, temperature=? WHERE rendez_vous_id=?");
            $stmt->execute([
                $diagnostic, $traitement, $notes,
                $poids ?: null, $taille ?: null, $tension ?: null, $temperature ?: null,
                $rdv_id_post
            ]);
            $consultation_id = $existing['id'];
        } else {
            // Créer la consultation
            $stmt = $pdo->prepare("INSERT INTO consultations
                (rendez_vous_id, diagnostic, traitement, notes, poids, taille, tension_arterielle, temperature)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $rdv_id_post, $diagnostic, $traitement, $notes,
                $poids ?: null, $taille ?: null, $tension ?: null, $temperature ?: null
            ]);
            $consultation_id = $pdo->lastInsertId();

            // Marquer le RDV comme effectué
            $stmt = $pdo->prepare("UPDATE rendez_vous SET statut='effectue' WHERE id=?");
            $stmt->execute([$rdv_id_post]);
        }

        // Enregistrer les prescriptions
        if (!empty($medicaments)) {
            // Supprimer anciennes prescriptions
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE consultation_id = ?");
            $stmt->execute([$consultation_id]);

            foreach ($medicaments as $i => $med) {
                if (!empty(trim($med))) {
                    $stmt = $pdo->prepare("INSERT INTO prescriptions
                        (consultation_id, medicament, posologie, duree, instructions)
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $consultation_id,
                        clean($med),
                        clean($posologies[$i] ?? ''),
                        clean($durees[$i] ?? ''),
                        clean($instructions[$i] ?? '')
                    ]);
                }
            }
        }

        // Notification au patient
        $stmt2 = $pdo->prepare("SELECT pa.utilisateur_id, r.date_rdv FROM rendez_vous r JOIN patients pa ON r.patient_id=pa.id WHERE r.id=?");
        $stmt2->execute([$rdv_id_post]);
        $info = $stmt2->fetch();
        if ($info) {
            $msg = "Votre consultation du ".date('d/m/Y', strtotime($info['date_rdv']))." a été enregistrée. Consultez votre dossier médical.";
            $stmt3 = $pdo->prepare("INSERT INTO notifications (utilisateur_id, message, type) VALUES (?,?,'info')");
            $stmt3->execute([$info['utilisateur_id'], $msg]);
        }

        setFlash('success', 'Consultation enregistrée avec succès !');
        header("Location: consultations.php");
        exit();
    }
}

// Historique des consultations du médecin
$stmt = $pdo->prepare("
    SELECT c.*, r.date_rdv, r.heure_rdv, r.id AS rdv_id,
           CONCAT(u.prenom, ' ', u.nom) AS patient_nom
    FROM consultations c
    JOIN rendez_vous r ON c.rendez_vous_id = r.id
    JOIN patients p ON r.patient_id = p.id
    JOIN utilisateurs u ON p.utilisateur_id = u.id
    WHERE r.medecin_id = ?
    ORDER BY c.date_consultation DESC
    LIMIT 20
");
$stmt->execute([$medecin_id]);
$historique = $stmt->fetchAll();

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
                <a href="consultations.php" class="nav-link active"><i class="bi bi-clipboard2-pulse"></i> Consultations</a>
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

        <?php if ($rdv): ?>
        <!-- ===== FORMULAIRE DE CONSULTATION ===== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-0">
                    <i class="bi bi-clipboard2-plus me-2"></i>Saisir une Consultation
                </h4>
                <small class="text-muted">
                    Patient : <strong><?= htmlspecialchars($rdv['patient_nom']) ?></strong>
                    — RDV du <?= date('d/m/Y', strtotime($rdv['date_rdv'])) ?>
                    à <?= date('H:i', strtotime($rdv['heure_rdv'])) ?>
                </small>
            </div>
            <a href="consultations.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour
            </a>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach ($erreurs as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <!-- Alerte allergies -->
        <?php if (!empty($rdv['allergies'])): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <strong>Allergie(s) connue(s) :</strong>&nbsp;<?= htmlspecialchars($rdv['allergies']) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">

            <div class="row g-4">
                <!-- Signes vitaux -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white fw-bold text-primary border-bottom">
                            <i class="bi bi-activity me-2"></i>Signes Vitaux
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Poids (kg)</label>
                                    <input type="number" step="0.1" class="form-control" name="poids"
                                           placeholder="Ex: 65.5">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Taille (cm)</label>
                                    <input type="number" step="0.1" class="form-control" name="taille"
                                           placeholder="Ex: 170">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Tension artérielle</label>
                                    <input type="text" class="form-control" name="tension_arterielle"
                                           placeholder="Ex: 120/80">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Température (°C)</label>
                                    <input type="number" step="0.1" class="form-control" name="temperature"
                                           placeholder="Ex: 37.2">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnostic et traitement -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white fw-bold text-primary border-bottom">
                            <i class="bi bi-search me-2"></i>Diagnostic <span class="text-danger">*</span>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="diagnostic" rows="5"
                                      placeholder="Entrez votre diagnostic..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-white fw-bold text-primary border-bottom">
                            <i class="bi bi-capsule me-2"></i>Traitement
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="traitement" rows="5"
                                      placeholder="Traitement prescrit..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white fw-bold text-primary border-bottom">
                            <i class="bi bi-sticky me-2"></i>Notes & Recommandations
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="notes" rows="3"
                                      placeholder="Notes supplémentaires, recommandations..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Prescriptions -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white fw-bold text-primary border-bottom d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-prescription2 me-2"></i>Prescriptions</span>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="ajouterPrescription()">
                                <i class="bi bi-plus-circle me-1"></i>Ajouter
                            </button>
                        </div>
                        <div class="card-body" id="prescriptions_container">
                            <!-- Ligne prescription ajoutée dynamiquement -->
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="rendez_vous.php" class="btn btn-outline-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-floppy me-2"></i>Enregistrer la Consultation
                    </button>
                </div>
            </div>
        </form>

        <?php else: ?>
        <!-- ===== HISTORIQUE DES CONSULTATIONS ===== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold text-primary mb-0">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Historique des Consultations
                </h4>
                <small class="text-muted"><?= count($historique) ?> dernière(s) consultation(s)</small>
            </div>
        </div>

        <?php if (!empty($historique)): ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Diagnostic</th>
                                <th>Traitement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $c): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($c['date_rdv'])) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($c['patient_nom']) ?></td>
                                <td class="small"><?= htmlspecialchars(mb_strimwidth($c['diagnostic'], 0, 60, '...')) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars(mb_strimwidth($c['traitement'] ?? '—', 0, 60, '...')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-clipboard2-x" style="font-size:4rem;opacity:0.2;"></i>
                <p class="mt-3">Aucune consultation enregistrée</p>
            </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<script>
let compteur = 0;
function ajouterPrescription() {
    const container = document.getElementById('prescriptions_container');
    const div = document.createElement('div');
    div.className = 'row g-2 align-items-end mb-3 prescription-ligne';
    div.id = 'presc_' + compteur;
    div.innerHTML = `
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Médicament</label>
            <input type="text" class="form-control form-control-sm" name="medicament[]" placeholder="Ex: Paracétamol" required>
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Posologie</label>
            <input type="text" class="form-control form-control-sm" name="posologie[]" placeholder="Ex: 500mg x3/jour">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Durée</label>
            <input type="text" class="form-control form-control-sm" name="duree[]" placeholder="Ex: 5 jours">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Instructions</label>
            <input type="text" class="form-control form-control-sm" name="instructions[]" placeholder="Ex: Après repas">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-outline-danger w-100"
                    onclick="document.getElementById('presc_${compteur}').remove()">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
    container.appendChild(div);
    compteur++;
}
// Ajouter une ligne par défaut
ajouterPrescription();
</script>

<?php require_once '../includes/footer.php'; ?>
