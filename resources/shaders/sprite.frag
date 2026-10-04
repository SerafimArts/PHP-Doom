#version 460
#ifdef RAY_SHADOWS
#extension GL_EXT_ray_query : require
#endif
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "shading.glsl"
#include "shadow.glsl"

layout(location = 0) in vec2 inTexel;
layout(location = 1) flat in uint inPicture;
layout(location = 2) flat in uint inLight;
layout(location = 3) flat in uint inFullBright;
layout(location = 4) flat in uvec2 inSize;
layout(location = 5) in vec3 inWorld;

layout(location = 0) out vec4 outColor;

void main()
{
    ivec2 texel = clamp(ivec2(floor(inTexel)), ivec2(0), ivec2(inSize) - 1);
    vec2 picture = fetchPicture(inPicture, texel);

    if (picture.y < 0.5)
    {
        discard;
    }

    int row = 0;
    vec3 glow = vec3(0.0);

    if (inFullBright == 0u)
    {
        // A sprite is a flat picture that faces the viewer, so does its light.
        vec3 normal = normalize(frame.eye.xyz - inWorld);

        glow = lightSurface(inWorld, normal, MASK_LEVEL);
        row = wallRow(lightStart(inLight, lightBoost(glow)), 1.0 / gl_FragCoord.w);
    }

    outColor = vec4(applyLight(shade(picture.x, row), glow), 1.0);
}
