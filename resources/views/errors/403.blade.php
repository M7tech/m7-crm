@include('errors.layout', [
    'code' => 403,
    'title' => 'You cannot access this page',
    'message' => 'Your account does not have permission for this action. Return to the CRM or ask your workspace administrator for access.',
])
