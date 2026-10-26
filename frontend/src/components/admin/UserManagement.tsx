import { useEffect, useState } from "react";
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import {
  createUser,
  getUsers,
  updateUserActive,
  type CreateUserData,
  type ManagedUser,
  type PaginatedUsers,
} from "../../api/users";

const initialForm: CreateUserData = {
  name: "",
  email: "",
  password: "",
  role: "DRIVER",
};

export default function UserManagement() {
  const [page, setPage] = useState(1);
  const [users, setUsers] = useState<PaginatedUsers | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [form, setForm] = useState<CreateUserData>({ ...initialForm });
  const [creating, setCreating] = useState(false);

  const [selectedUser, setSelectedUser] = useState<ManagedUser | null>(null);
  const [updating, setUpdating] = useState(false);

  async function loadUsers() {
    setLoading(true);
    setError(null);

    try {
      const result = await getUsers(page);
      setUsers(result);
    } catch {
      setError("Impossible de charger les utilisateurs.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let cancelled = false;

    getUsers(page)
      .then((result) => {
        if (!cancelled) {
          setUsers(result);
          setError(null);
          setLoading(false);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError("Impossible de charger les utilisateurs.");
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [page]);

  async function handleCreate(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setCreating(true);
    setError(null);
    setSuccess(null);

    try {
      await createUser(form);
      setForm({ ...initialForm });
      setSuccess("Compte utilisateur créé avec succès.");
      await loadUsers();
    } catch {
      setError(
        "Création impossible. Vérifiez les informations, notamment l'adresse e-mail et le mot de passe.",
      );
    } finally {
      setCreating(false);
    }
  }

  async function handleChangeActive() {
    if (!selectedUser) return;

    setUpdating(true);
    setError(null);
    setSuccess(null);

    try {
      const nextActive = !selectedUser.is_active;

      await updateUserActive(selectedUser.id, nextActive);

      setSuccess(
        nextActive
          ? "Compte réactivé avec succès."
          : "Compte désactivé avec succès.",
      );

      setSelectedUser(null);
      await loadUsers();
    } catch {
      setError("Impossible de modifier le statut du compte.");
    } finally {
      setUpdating(false);
    }
  }

  return (
    <Stack spacing={3}>
      <Typography variant="h6">
        Gestion des utilisateurs
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Typography variant="subtitle1" sx={{ mb: 2 }}>
          Créer un utilisateur
        </Typography>

        <Box component="form" onSubmit={handleCreate}>
          <Stack spacing={2}>
            <TextField
              label="Nom"
              value={form.name}
              required
              fullWidth
              onChange={(event) =>
                setForm({ ...form, name: event.target.value })
              }
            />

            <TextField
              label="Adresse e-mail"
              type="email"
              value={form.email}
              required
              fullWidth
              onChange={(event) =>
                setForm({ ...form, email: event.target.value })
              }
            />

            <TextField
              label="Mot de passe"
              type="password"
              value={form.password}
              required
              fullWidth
              helperText="12 caractères minimum."
              onChange={(event) =>
                setForm({ ...form, password: event.target.value })
              }
            />

            <TextField
              select
              label="Rôle"
              value={form.role}
              fullWidth
              onChange={(event) =>
                setForm({
                  ...form,
                  role: event.target.value as CreateUserData["role"],
                })
              }
            >
              <MenuItem value="DRIVER">DRIVER — Chauffeur</MenuItem>
              <MenuItem value="DISPATCHER">DISPATCHER — Répartiteur</MenuItem>
            </TextField>

            <Button
              type="submit"
              variant="contained"
              disabled={creating}
            >
              {creating ? "Création..." : "Créer le compte"}
            </Button>
          </Stack>
        </Box>
      </Paper>

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Typography variant="subtitle1" sx={{ mb: 2 }}>
          Comptes utilisateurs
        </Typography>

        {loading ? (
          <CircularProgress size={24} />
        ) : users?.data.length === 0 ? (
          <Typography>Aucun utilisateur trouvé.</Typography>
        ) : (
          <Stack spacing={2}>
            {users?.data.map((user) => (
              <Box
                key={user.id}
                sx={{
                  p: 2,
                  border: 1,
                  borderColor: "divider",
                  borderRadius: 1,
                }}
              >
                <Stack
                  direction={{ xs: "column", sm: "row" }}
                  spacing={2}
                  sx={{
                    alignItems: { sm: "center" },
                    justifyContent: "space-between",
                  }}
                >
                  <Box>
                    <Typography sx={{ fontWeight: "bold" }}>
                      {user.name}
                    </Typography>
                    <Typography variant="body2">
                      {user.email}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {user.role} — {user.is_active ? "Actif" : "Inactif"}
                    </Typography>
                  </Box>

                  {user.role !== "ADMIN" && (
                    <Button
                      variant="outlined"
                      color={user.is_active ? "error" : "success"}
                      onClick={() => setSelectedUser(user)}
                    >
                      {user.is_active ? "Désactiver" : "Réactiver"}
                    </Button>
                  )}
                </Stack>
              </Box>
            ))}

            <Stack
              direction="row"
              spacing={2}
              sx={{
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <Button
                disabled={page <= 1}
                onClick={() => setPage((current) => current - 1)}
              >
                Précédent
              </Button>

              <Typography variant="body2">
                Page {users?.current_page} / {users?.last_page}
              </Typography>

              <Button
                disabled={!users || page >= users.last_page}
                onClick={() => setPage((current) => current + 1)}
              >
                Suivant
              </Button>
            </Stack>
          </Stack>
        )}
      </Paper>

      <Dialog
        open={selectedUser !== null}
        onClose={() => {
          if (!updating) setSelectedUser(null);
        }}
      >
        <DialogTitle>
          Confirmer la modification
        </DialogTitle>

        <DialogContent>
          <DialogContentText>
            Voulez-vous vraiment{" "}
            {selectedUser?.is_active ? "désactiver" : "réactiver"} le compte
            de {selectedUser?.name} ?
          </DialogContentText>
        </DialogContent>

        <DialogActions>
          <Button
            disabled={updating}
            onClick={() => setSelectedUser(null)}
          >
            Annuler
          </Button>

          <Button
            color={selectedUser?.is_active ? "error" : "success"}
            variant="contained"
            disabled={updating}
            onClick={() => void handleChangeActive()}
          >
            {updating ? "Enregistrement..." : "Confirmer"}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}