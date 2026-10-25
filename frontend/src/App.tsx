import { useEffect, useState, type SubmitEvent } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Container,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import { getCurrentUser, login, logout, type AuthUser } from './api/auth'
import MissionList from './components/missions/MissionList'
import MissionCreateForm from './components/missions/MissionCreateForm'

function App() {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [checkingSession, setCheckingSession] = useState(true)
  const [error, setError] = useState('')

  // Trigger a mission list refresh after a successful creation.
  const [missionRefreshKey, setMissionRefreshKey] = useState(0)

  useEffect(() => {
    let active = true

    getCurrentUser()
      .then((currentUser) => {
        if (active) setUser(currentUser)
      })
      .catch(() => {
        // No authenticated session: display the login form.
      })
      .finally(() => {
        if (active) setCheckingSession(false)
      })

    return () => {
      active = false
    }
  }, [])

  async function handleLogin(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    setLoading(true)

    try {
      const authenticatedUser = await login({ email, password })
      setUser(authenticatedUser)
      setPassword('')
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 429) {
        setError('Trop de tentatives. Réessayez dans quelques instants.')
      } else {
        setError('Connexion impossible. Vérifiez vos identifiants.')
      }
    } finally {
      setLoading(false)
    }
  }

  async function handleLogout() {
    setError('')
    setLoading(true)

    try {
      await logout()
      setUser(null)
      setPassword('')
    } catch {
      setError('La déconnexion a échoué. Veuillez réessayer.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        py: { xs: 3, sm: 6 },
        px: 2,
        bgcolor: 'background.default',
      }}
    >
      <Container maxWidth="sm">
        <Paper
          elevation={0}
          sx={{
            p: { xs: 3, sm: 5 },
            border: 1,
            borderColor: 'divider',
            borderRadius: 3,
            boxShadow: '0 18px 50px rgba(15, 23, 42, 0.06)',
          }}
        >
          <Stack spacing={3}>
            <Stack spacing={1} sx={{ alignItems: 'center' }}>
              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: 64,
                  height: 64,
                  borderRadius: 2,
                  bgcolor: 'primary.main',
                  color: 'primary.contrastText',
                }}
              >
                <LockOutlinedIcon fontSize="large" />
              </Box>

              <Typography
                variant="h4"
                component="h1"
                sx={{ fontWeight: 700 }}
              >
                GML-SHIELD
              </Typography>

              <Typography sx={{ color: 'text.secondary', textAlign: 'center' }}>
                Gestion sécurisée des missions de transport
              </Typography>
            </Stack>

            {checkingSession ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress aria-label="Vérification de la session" />
              </Box>
            ) : user ? (
              <Stack spacing={3}>
                <Alert severity="success">
                  Vous êtes connecté(e).
                </Alert>

                <Box>
                  <Typography variant="h6">
                    Bienvenue, {user.name}
                  </Typography>
                  <Typography color="text.secondary">
                    Rôle : {user.role}
                  </Typography>
                </Box>

                {(user.role === 'ADMIN' || user.role === 'DISPATCHER') && (
                  <MissionCreateForm
                    onMissionCreated={() => {
                      setMissionRefreshKey((currentKey) => currentKey + 1)
                    }}
                  />
                )}

                <MissionList key={missionRefreshKey} user={user} />

                {error && <Alert severity="error">{error}</Alert>}

                <Button
                  variant="outlined"
                  color="primary"
                  onClick={handleLogout}
                  disabled={loading}
                  fullWidth
                >
                  {loading ? 'Déconnexion...' : 'Se déconnecter'}
                </Button>
              </Stack>
            ) : (
              <Box component="form" onSubmit={handleLogin}>
                <Stack spacing={2.5}>
                  <Typography variant="h6" component="h2">
                    Connexion
                  </Typography>

                  <TextField
                    label="Adresse e-mail"
                    type="email"
                    autoComplete="username"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    required
                    fullWidth
                  />

                  <TextField
                    label="Mot de passe"
                    type="password"
                    autoComplete="current-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    required
                    fullWidth
                  />

                  {error && <Alert severity="error">{error}</Alert>}

                  <Button
                    type="submit"
                    variant="contained"
                    size="large"
                    disabled={loading}
                    fullWidth
                  >
                    {loading ? 'Connexion...' : 'Se connecter'}
                  </Button>
                </Stack>
              </Box>
            )}

            <Typography
              variant="caption"
              sx={{ color: 'text.secondary', textAlign: 'center' }}
            >
              GreenMove Logistics — Accès réservé aux utilisateurs autorisés
            </Typography>
          </Stack>
        </Paper>
      </Container>
    </Box>
  )
}

export default App
