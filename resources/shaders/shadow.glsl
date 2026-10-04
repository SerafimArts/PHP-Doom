// Light of the lamps, the flash of a shot, fireballs and explosions with shadows, by ray queries against the level and
// the solid things. Without RAY_SHADOWS the surfaces keep the light of their sector. The shader that includes this file
// enables GL_EXT_ray_query itself, the directive has to come first.

// Rays of a surface see the level and the things, rays of a sprite the level only.
const uint MASK_SURFACE = 0xFFu;
const uint MASK_LEVEL = 0x01u;

#ifdef RAY_SHADOWS

// How many steps of the colormap the light of full strength adds to the light of the sector.
const float BOOST_LEVELS = 7.0;

// How strongly the color of the lights multiplies the color of a surface.
const float GLOW_STRENGTH = 1.1;

// The size of a light: a ray goes to a point inside of it, so the shadow edge is soft.
const float LIGHT_SIZE = 6.0;

struct Light
{
    // xyz: where it shines from, w: how far it reaches, zero for no light
    vec4 position;
    // rgb: the color with the strength
    vec4 color;
};

layout(set = 0, binding = 11) uniform accelerationStructureEXT shadowScene;

layout(std430, set = 0, binding = 12) readonly buffer Lights
{
    Light lights[MAX_LIGHTS];
};

uint hashStep(uint value)
{
    uint state = value * 747796405u + 2891336453u;
    uint word = ((state >> ((state >> 28u) + 4u)) ^ state) * 277803737u;

    return (word >> 22u) ^ word;
}

// The color that the lights add to a point of a surface: every light that reaches the point and is not covered.
vec3 lightSurface(vec3 world, vec3 normal, uint mask)
{
    if (frame.view.w < 0.5)
    {
        return vec3(0.0);
    }

    uvec2 pixel = uvec2(gl_FragCoord.xy);
    uint seed = hashStep(pixel.x + hashStep(pixel.y));
    vec3 total = vec3(0.0);

    for (int i = 0; i < MAX_LIGHTS; ++i)
    {
        Light light = lights[i];
        float radius = light.position.w;

        if (radius <= 0.0)
        {
            continue;
        }

        seed = hashStep(seed + uint(i));
        vec3 jitter = (vec3(float(seed & 1023u), float((seed >> 10u) & 1023u), float((seed >> 20u) & 1023u)) / 511.5 - 1.0);

        vec3 toLight = light.position.xyz - world;
        float distance = length(toLight);

        if (distance >= radius || distance < 1.0)
        {
            continue;
        }

        // The size of the light seen from the point: far lights are points, near ones do not flicker.
        toLight += jitter * min(LIGHT_SIZE, distance * 0.06);
        vec3 direction = normalize(toLight);
        float facing = clamp(dot(normal, direction) * 0.8 + 0.2, 0.0, 1.0);

        if (facing <= 0.0)
        {
            continue;
        }

        float x = distance / radius;
        float window = 1.0 - x * x;
        float falloff = window * window / (1.0 + 5.0 * x * x);

        rayQueryEXT query;
        rayQueryInitializeEXT(query, shadowScene, gl_RayFlagsOpaqueEXT | gl_RayFlagsTerminateOnFirstHitEXT, mask, world + normal * 0.75, 0.0, direction, distance - 3.0);

        while (rayQueryProceedEXT(query))
        {
        }

        if (rayQueryGetIntersectionTypeEXT(query, true) != gl_RayQueryCommittedIntersectionNoneEXT)
        {
            continue;
        }

        total += light.color.rgb * (falloff * facing);
    }

    return total;
}

// Steps of the colormap that the light adds, so that a dark corridor shows what the light touches.
int lightBoost(vec3 glow)
{
    float strength = dot(glow, vec3(0.3, 0.59, 0.11));

    return int(min(strength, 1.0) * BOOST_LEVELS + 0.5);
}

// The color of a lit surface: the color of the light multiplies the color of the picture.
vec3 applyLight(vec3 color, vec3 glow)
{
    return color * (vec3(1.0) + glow * GLOW_STRENGTH);
}

#else

vec3 lightSurface(vec3 world, vec3 normal, uint mask)
{
    return vec3(0.0);
}

int lightBoost(vec3 glow)
{
    return 0;
}

vec3 applyLight(vec3 color, vec3 glow)
{
    return color;
}

#endif
