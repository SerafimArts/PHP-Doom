#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"

layout(location = 0) in vec2 inPosition;
layout(location = 1) in uint inSector;

layout(location = 0) out vec2 outPosition;
layout(location = 1) flat out uint outPicture;
layout(location = 2) flat out uint outLight;
layout(location = 3) flat out uint outIsSky;
layout(location = 4) out vec3 outWorld;
layout(location = 5) flat out vec3 outNormal;

// Instance 0 draws the floors, instance 1 the ceilings.
void main()
{
    Sector sector = sectors[inSector];
    bool isCeiling = gl_InstanceIndex == 1;

    float z = isCeiling ? sector.ceilingHeight : sector.floorHeight;
    uint sky = isCeiling ? SECTOR_CEILING_IS_SKY : SECTOR_FLOOR_IS_SKY;

    vec3 world = vec3(inPosition, z);

    gl_Position = frame.viewProjection * vec4(world, 1.0);

    outPosition = inPosition;
    outWorld = world;
    outNormal = vec3(0.0, 0.0, isCeiling ? -1.0 : 1.0);
    outPicture = isCeiling ? sector.ceilingPicture : sector.floorPicture;
    outLight = sector.light;
    outIsSky = (sector.flags & sky) != 0u ? 1u : 0u;
}
