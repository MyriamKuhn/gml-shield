
import { useEffect, useRef, useState, type FormEvent } from 'react'
import {
  Alert,
  Avatar,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  LinearProgress,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'

import PeopleOutlineRoundedIcon from '@mui/icons-material/PeopleOutlineRounded'
import PersonAddAltOutlinedIcon from '@mui/icons-material/PersonAddAltOutlined'
import ChevronLeftRoundedIcon from '@mui/icons-material/ChevronLeftRounded'
import ChevronRightRoundedIcon from '@mui/icons-material/ChevronRightRounded'
import CheckCircleOutlineRoundedIcon from '@mui/icons-material/CheckCircleOutlineRounded'
import BlockOutlinedIcon from '@mui/icons-material/BlockOutlined'

import {
  createUser,
  getUsers,
  updateUserActive,
  type CreateUserData,
  type ManagedUser,
  type PaginatedUsers,
} from '../../api/users'

const initialForm: CreateUserData = {
  name: '',
  email: '',
  password: '',
  role: 'DRIVER',
}

function getRoleLabel(role: ManagedUser['role']) {
  switch (role) {
    case 'ADMIN':
      return 'Administrateur'
    case 'DISPATCHER':
      return 'Répartiteur'
    case 'DRIVER':
      return 'Chauffeur'
  }
}

function getInitials(name: string) {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
}

export default function UserManagement() {
  const [page, setPage] = useState(1)
  const [users, setUsers] = useState<PaginatedUsers | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const [form, setForm] = useState<CreateUserData>({
    ...initialForm,
  })
  const [creating, setCreating] = useState(false)
  const [formVersion, setFormVersion] = useState(0)

  const [selectedUser, setSelectedUser] = useState<ManagedUser | null>(null)
  const [updating, setUpdating] = useState(false)

  // Prevent an outdated API response from overwriting newer data.
  const requestId = useRef(0)

  useEffect(() => {
    let active = true
    const currentRequest = ++requestId.current

    getUsers(page)
      .then((result) => {
        if (!active || currentRequest !== requestId.current) return

        setUsers(result)
        setError(null)
      })
      .catch(() => {
        if (!active || currentRequest !== requestId.current) return

        setError('Impossible de charger les utilisateurs.')
      })
      .finally(() => {
        if (active && currentRequest === requestId.current) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [page])

  // Refresh the data without clearing or hiding the existing list.
  async function refreshUsers(targetPage = page) {
    const currentRequest = ++requestId.current

    setLoading(true)

    try {
      const result = await getUsers(targetPage)

      if (currentRequest === requestId.current) {
        setUsers(result)
      }

      return true
    } catch {
      if (currentRequest === requestId.current) {
        setError('Impossible d’actualiser la liste des utilisateurs.')
      }
      return false
    } finally {
      if (currentRequest === requestId.current) {
        setLoading(false)
      }
    }
  }

  async function handleCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (creating) return

    setCreating(true)
    setError(null)
    setSuccess(null)

    try {
      await createUser(form)

      // Remount only the creation form to reset its DOM fields.
      // The user list remains mounted and visible.
      setForm({ ...initialForm })
      setFormVersion((current) => current + 1)

      setSuccess('Compte utilisateur créé avec succès.')

      if (page !== 1) {
        setPage(1)
      } else {
        await refreshUsers(1)
      }
    } catch {
      setError(
        "Création impossible. Vérifiez les informations, notamment l'adresse e-mail et le mot de passe.",
      )
    } finally {
      setCreating(false)
    }
  }

  async function handleChangeActive() {
    if (!selectedUser || updating) return

    setUpdating(true)
    setError(null)
    setSuccess(null)

    try {
      const nextActive = !selectedUser.is_active

      await updateUserActive(selectedUser.id, nextActive)

      setSelectedUser(null)
      setSuccess(
        nextActive
          ? 'Compte réactivé avec succès.'
          : 'Compte désactivé avec succès.',
      )

      await refreshUsers()
    } catch {
      setError('Impossible de modifier le statut du compte.')
    } finally {
      setUpdating(false)
    }
  }

  return (
    <Stack spacing={3}>
      {/* Page heading */}
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          gap: 2,
        }}
      >
        <Box
          sx={{
            width: 48,
            height: 48,
            borderRadius: 2,
            bgcolor: '#E8F5F2',
            color: '#00897B',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexShrink: 0,
          }}
        >
          <PeopleOutlineRoundedIcon sx={{ fontSize: 27 }} />
        </Box>

        <Box>
          <Typography
            variant="h6"
            component="h3"
            sx={{ fontWeight: 700 }}
          >
            Gestion des utilisateurs
          </Typography>

          <Typography variant="body2" color="text.secondary">
            Gérez les comptes et les accès à GML-SHIELD.
          </Typography>
        </Box>
      </Box>

      {error && <Alert severity="error">{error}</Alert>}
      {success && <Alert severity="success">{success}</Alert>}

      {/* Account creation */}
      <Paper
        variant="outlined"
        sx={{
          p: { xs: 2, sm: 3 },
          borderRadius: 2.5,
          bgcolor: '#FFFFFF',
        }}
      >
        <Stack spacing={3}>
          <Box
            sx={{
              display: 'flex',
              alignItems: 'center',
              gap: 1.5,
            }}
          >
            <PersonAddAltOutlinedIcon sx={{ color: '#00897B' }} />

            <Typography
              variant="subtitle1"
              sx={{ fontWeight: 700 }}
            >
              Créer un utilisateur
            </Typography>
          </Box>

          <Box
            component="form"
            key={formVersion}
            onSubmit={handleCreate}
            autoComplete="off"
          >
            <Stack spacing={3}>
              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: {
                    xs: '1fr',
                    sm: 'repeat(2, minmax(0, 1fr))',
                  },
                  gap: 2.5,
                }}
              >
                <TextField
                  label="Nom complet"
                  name="gml-new-account-full-name"
                  value={form.name}
                  required
                  fullWidth
                  disabled={creating}
                  autoComplete="off"
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      name: event.target.value,
                    }))
                  }
                />

                <TextField
                  label="Adresse e-mail"
                  type="email"
                  name="gml-new-account-email"
                  value={form.email}
                  required
                  fullWidth
                  disabled={creating}
                  autoComplete="off"
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      email: event.target.value,
                    }))
                  }
                />

                <TextField
                  label="Mot de passe temporaire"
                  type="password"
                  name="gml-new-account-password"
                  value={form.password}
                  required
                  fullWidth
                  disabled={creating}
                  autoComplete="new-password"
                  helperText="12 caractères minimum."
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      password: event.target.value,
                    }))
                  }
                />

                <TextField
                  select
                  label="Rôle"
                  name="gml-new-account-role"
                  value={form.role}
                  fullWidth
                  disabled={creating}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      role: event.target.value as CreateUserData['role'],
                    }))
                  }
                >
                  <MenuItem value="DRIVER">
                    Chauffeur
                  </MenuItem>
                  <MenuItem value="DISPATCHER">
                    Répartiteur
                  </MenuItem>
                </TextField>
              </Box>

              <Box
                sx={{
                  display: 'flex',
                  justifyContent: 'flex-end',
                }}
              >
                <Button
                  type="submit"
                  variant="contained"
                  disabled={creating}
                  startIcon={
                    creating ? (
                      <CircularProgress size={17} color="inherit" />
                    ) : (
                      <PersonAddAltOutlinedIcon />
                    )
                  }
                  sx={{
                    minHeight: 46,
                    width: { xs: '100%', sm: 'auto' },
                    px: 3,
                  }}
                >
                  {creating ? 'Création en cours...' : 'Créer le compte'}
                </Button>
              </Box>
            </Stack>
          </Box>
        </Stack>
      </Paper>

      {/* Existing accounts */}
      <Paper
        variant="outlined"
        sx={{
          borderRadius: 2.5,
          overflow: 'hidden',
          bgcolor: '#FFFFFF',
        }}
      >
        <Box
          sx={{
            px: { xs: 2, sm: 3 },
            py: 2.5,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: 2,
            flexWrap: 'wrap',
          }}
        >
          <Box>
            <Typography
              variant="subtitle1"
              sx={{ fontWeight: 700 }}
            >
              Comptes utilisateurs
            </Typography>

            <Typography variant="body2" color="text.secondary">
              Consultez et modifiez le statut des comptes.
            </Typography>
          </Box>

          {users && (
            <Chip
              label={`${users.total} compte${users.total > 1 ? 's' : ''}`}
              variant="outlined"
              sx={{
                color: '#00897B',
                borderColor: '#B8DFD7',
                fontWeight: 600,
              }}
            />
          )}
        </Box>

        {/* Thin progress indicator instead of replacing the list */}
        <Box sx={{ height: 3 }}>
          {loading && <LinearProgress />}
        </Box>

        {loading && users === null ? (
          <Box
            sx={{
              py: 6,
              display: 'flex',
              justifyContent: 'center',
            }}
          >
            <CircularProgress aria-label="Chargement des utilisateurs" />
          </Box>
        ) : (
          <Stack spacing={0}>
            {users?.data.length === 0 && (
              <Box sx={{ p: 4, textAlign: 'center' }}>
                <Typography color="text.secondary">
                  Aucun utilisateur trouvé.
                </Typography>
              </Box>
            )}

            {users?.data.map((managedUser, index) => (
              <Box
                key={managedUser.id}
                sx={{
                  px: { xs: 2, sm: 3 },
                  py: 2.5,
                  borderTop: index === 0 ? 'none' : '1px solid',
                  borderColor: 'divider',
                }}
              >
                <Box
                  sx={{
                    display: 'flex',
                    alignItems: { xs: 'flex-start', sm: 'center' },
                    justifyContent: 'space-between',
                    gap: 2,
                    flexWrap: 'wrap',
                  }}
                >
                  <Box
                    sx={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: 2,
                      minWidth: 0,
                      flex: 1,
                    }}
                  >
                    <Avatar
                      sx={{
                        width: 46,
                        height: 46,
                        bgcolor: '#E8F5F2',
                        color: '#00897B',
                        fontWeight: 700,
                        flexShrink: 0,
                      }}
                    >
                      {getInitials(managedUser.name)}
                    </Avatar>

                    <Box sx={{ minWidth: 0 }}>
                      <Typography sx={{ fontWeight: 700 }}>
                        {managedUser.name}
                      </Typography>

                      <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ overflowWrap: 'anywhere' }}
                      >
                        {managedUser.email}
                      </Typography>

                      <Box
                        sx={{
                          display: 'flex',
                          alignItems: 'center',
                          flexWrap: 'wrap',
                          gap: 1,
                          mt: 1,
                        }}
                      >
                        <Chip
                          size="small"
                          variant="outlined"
                          label={getRoleLabel(managedUser.role)}
                        />

                        <Chip
                          size="small"
                          icon={
                            managedUser.is_active ? (
                              <CheckCircleOutlineRoundedIcon />
                            ) : (
                              <BlockOutlinedIcon />
                            )
                          }
                          label={
                            managedUser.is_active ? 'Actif' : 'Inactif'
                          }
                          color={
                            managedUser.is_active ? 'success' : 'default'
                          }
                          variant="outlined"
                        />
                      </Box>
                    </Box>
                  </Box>

                  {managedUser.role !== 'ADMIN' && (
                    <Button
                      variant="outlined"
                      size="small"
                      color={managedUser.is_active ? 'error' : 'success'}
                      disabled={loading || updating}
                      onClick={() => setSelectedUser(managedUser)}
                      sx={{ flexShrink: 0 }}
                    >
                      {managedUser.is_active ? 'Désactiver' : 'Réactiver'}
                    </Button>
                  )}
                </Box>
              </Box>
            ))}

            {users && (
              <Box
                sx={{
                  px: { xs: 2, sm: 3 },
                  py: 2.5,
                  borderTop: '1px solid',
                  borderColor: 'divider',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  flexWrap: 'wrap',
                  gap: 2,
                }}
              >
                <Typography variant="body2" color="text.secondary">
                  Page {users.current_page} sur {users.last_page}
                </Typography>

                <Stack direction="row" spacing={1}>
                  <Button
                    variant="outlined"
                    size="small"
                    startIcon={<ChevronLeftRoundedIcon />}
                    disabled={loading || page <= 1}
                    onClick={() =>
                      setPage((current) => Math.max(1, current - 1))
                    }
                  >
                    Précédent
                  </Button>

                  <Button
                    variant="outlined"
                    size="small"
                    endIcon={<ChevronRightRoundedIcon />}
                    disabled={loading || page >= users.last_page}
                    onClick={() => setPage((current) => current + 1)}
                  >
                    Suivant
                  </Button>
                </Stack>
              </Box>
            )}
          </Stack>
        )}
      </Paper>

      {/* Account activation/deactivation confirmation */}
      <Dialog
        open={selectedUser !== null}
        onClose={() => {
          if (!updating) setSelectedUser(null)
        }}
        fullWidth
        maxWidth="xs"
      >
        <DialogTitle sx={{ fontWeight: 700 }}>
          Confirmer la modification
        </DialogTitle>

        <DialogContent>
          <DialogContentText>
            Voulez-vous vraiment{' '}
            {selectedUser?.is_active ? 'désactiver' : 'réactiver'} le
            compte de <strong>{selectedUser?.name}</strong> ?
          </DialogContentText>
        </DialogContent>

        <DialogActions sx={{ p: 2.5 }}>
          <Button
            disabled={updating}
            onClick={() => setSelectedUser(null)}
          >
            Annuler
          </Button>

          <Button
            color={selectedUser?.is_active ? 'error' : 'success'}
            variant="contained"
            disabled={updating}
            onClick={() => void handleChangeActive()}
          >
            {updating ? 'Enregistrement...' : 'Confirmer'}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}
