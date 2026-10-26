import { useState } from 'react'
import {
  Button,
  Paper,
  Stack,
  Typography,
} from '@mui/material'

import type { AuthUser } from '../../api/auth'
import AuditLogList from './AuditLogList'
import UserManagement from './UserManagement'
import MissionList from '../missions/MissionList'
import MissionCreateForm from '../missions/MissionCreateForm'

interface AdminDashboardProps {
  user: AuthUser
}

type AdminSection = 'audit' | 'missions' | 'users'

export default function AdminDashboard({
  user,
}: AdminDashboardProps) {
  const [section, setSection] = useState<AdminSection>('audit')
  const [missionRefreshKey, setMissionRefreshKey] = useState(0)

  return (
    <Stack spacing={3}>
      <Paper variant="outlined" sx={{ p: 3, borderRadius: 2 }}>
        <Stack spacing={2}>
          <Typography variant="h6" component="h2">
            Espace administrateur
          </Typography>

          <Typography variant="body2" color="text.secondary">
            Supervision et sécurité de GML-SHIELD.
          </Typography>

          <Stack
            direction={{ xs: 'column', sm: 'row' }}
            spacing={2}
          >
            <Button
              variant={section === 'users' ? 'contained' : 'outlined'}
              onClick={() => setSection('users')}
            >
              Gestion des utilisateurs
            </Button>

            <Button
              variant={section === 'audit' ? 'contained' : 'outlined'}
              onClick={() => setSection('audit')}
            >
              Journal d'audit
            </Button>

            <Button
              variant={section === 'missions' ? 'contained' : 'outlined'}
              onClick={() => setSection('missions')}
            >
              Supervision des missions
            </Button>

            <Button
              variant="outlined"
              href={`${import.meta.env.VITE_API_URL || "http://localhost:8000"}/docs/api`}
              target="_blank"
              rel="noopener noreferrer"
            >
              Documentation API (Swagger)
            </Button>
          </Stack>
        </Stack>
      </Paper>

      {section === 'audit' && <AuditLogList />}

      {section === 'users' && <UserManagement />}

      {section === 'missions' && (
        <Stack spacing={3}>
          <Typography variant="h6" component="h2">
            Supervision des missions
          </Typography>

          <Typography variant="body2" color="text.secondary">
            Gestion exceptionnelle des missions selon les
            autorisations accordées par Laravel.
          </Typography>

          <MissionCreateForm
            onMissionCreated={() => {
              setMissionRefreshKey((current) => current + 1)
            }}
          />

          <MissionList
            key={missionRefreshKey}
            user={user}
          />
        </Stack>
      )}
    </Stack>
  )
}