const userRoot = ['user'] as const;

export const profileQueryKeys = {
    all: userRoot,
    profile: () => [...userRoot, 'profile'] as const,
} as const;
